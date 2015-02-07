<?php namespace Mikhailkozlov\RetsLaravel\Rets;

use Aws\CloudFront\Exception\Exception;
use Guzzle\Http\Client,
    Illuminate\Events\Dispatcher,
    Illuminate\Config\Repository,
    Mikhailkozlov\RetsLaravel\Rets\Exceptions\ConnectionException;
use Illuminate\Support\Collection;


class RetsRepository implements RetsInterface
{
    protected $client;
    protected $config;
    protected $events;
    public    $lastError;

    function __construct(Dispatcher $event, Repository $config)
    {
        $this->config = $config;
        $this->events = $event;
        $this->connect();
    }


    public function connect()
    {
        // going to store defaults just in case
        $conf = $this->config->get('rets-laravel::connection', array(
            'username' => '',
            'password' => '',
            'url '     => '',
            'version'  => '',
        ));

        if (!array_key_exists('url', $conf) || empty($conf['url'])) {
            throw new ConnectionException('No URL provided in config file');
        }

        if (!array_key_exists('username', $conf) || empty($conf['username'])) {
            throw new ConnectionException('No URL provided in config file');
        }

        if (!array_key_exists('password', $conf)) {
            throw new ConnectionException('No URL provided in config file');
        }

        $this->client = new Client(
            $conf['url'], array(
                'version'         => $conf['version'],
                'request.options' => array(
                    //'headers' => array('Foo' => 'Bar'),
                    //'query'   => array('testing' => '123'),
                    'auth' => array($conf['username'], $conf['password'], 'digest'),
                    //'proxy'   => 'tcp://localhost:80'
                )
            )
        );
        $this->client->setDefaultOption('exceptions', false);
        $this->client->setDefaultOption('query', array('Format' => 'STANDARD-XML'));
        $login = $this->client->get('Login')->send();


        // we need to find out what is the correct output once login is a go
        if ((string)$login->xml()->attributes()->ReplyText != 'Success') {
            throw new ConnectionException((string)$login->xml()->attributes()->ReplyText);
        }
    }

    /**
     *
     * The response from this RETS request contains information about all of the Resources available on the RETS server. Note the KeyField for the “Property” Resource (assuming “LIST_1″ for the rest of these examples).
     * http://retsgw.flexmls.com/rets2_1/GetMetadata?Type=METADATA-RESOURCE&ID=0&Format=COMPACT
     *
     * @param int $resourceID
     */
    public function getResource($resourceID = 0)
    {
        $resources = $this->client->get(
            'GetMetadata',
            array(),
            array(
                'query' => array(
                    'Type' => 'METADATA-RESOURCE',
                    'ID'   => $resourceID,
                )
            )
        );
        $res = $resources->send();

        // store output just in case
        \File::put(app_path() . '/storage/resource_' . $resourceID . '.xml', $res->getBody(true));

        $resourcesData = $res->xml();

        if ((string)$resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array)$resourcesData->xpath('METADATA/METADATA-RESOURCE/Resource');

        // return collection
        return new Collection($result);
    }

    public function getClass($classID = null)
    {
        if (is_null($classID)) {
            return null;
        }

        $resources = $this->client->get(
            'GetMetadata',
            array(),
            array(
                'query' => array(
                    'Type' => 'METADATA-CLASS',
                    'ID'   => $classID,
                )
            )
        );

        $res = $resources->send();

        // store output just in case
        \File::put(app_path() . '/storage/class_' . $classID . '.xml', $res->getBody(true));

        $resourcesData = $res->xml();

        if ((string)$resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array)$resourcesData->xpath('METADATA/METADATA-CLASS/Class');

        // return collection
        return new Collection($result);
    }

    /**
     *
     * http://retsgw.flexmls.com/rets2_1/GetMetadata?Type=METADATA-TABLE&ID=Property:A&Format=COMPACT
     *
     * @param $ResourceID
     * @param $classID
     *
     * @return Collection|null
     */
    public function getTable($ResourceID, $classID)
    {
        if (is_null($classID)) {
            return null;
        }

        $resources = $this->client->get(
            'GetMetadata',
            array(),
            array(
                'query' => array(
                    'Type' => 'METADATA-TABLE',
                    'ID'   => $ResourceID . ':' . $classID,
                )
            )
        );

        $res = $resources->send();
        // store output just in case
        \File::put(app_path() . '/storage/table_' . $ResourceID . '_' . $classID . '.xml', $res->getBody(true));


        $resourcesData = $res->xml();
        if ((string)$resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array)$resourcesData->xpath('METADATA/METADATA-TABLE/Field');

        // return collection
        return new Collection($result);
    }

    /**
     *
     * http://retsgw.flexmls.com/rets2_1/GetMetadata?Type=METADATA-LOOKUP_TYPE&ID=Property:20070913202543158090000000&Format=COMPACT
     *
     * @param $ResourceID
     * @param $fieldID
     *
     * @return Collection|null
     *
     *
     */
    public function getFieldMetadata($ResourceID, $fieldID)
    {
        if (is_null($fieldID)) {
            return null;
        }

        $resources = $this->client->get(
            'GetMetadata',
            array(),
            array(
                'query' => array(
                    'Type' => 'METADATA-LOOKUP_TYPE',
                    'ID'   => $ResourceID . ':' . $fieldID,
                )
            )
        );

        $res = $resources->send();
        // store output just in case
        \File::put(app_path() . '/storage/field_' . $ResourceID . '_' . $fieldID . '.xml', $res->getBody(true));

        $resourcesData = $res->xml();
        if ((string)$resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array)$resourcesData->xpath('METADATA/METADATA-LOOKUP_TYPE/Field');

        // return collection
        return new Collection($result);
    }

    /**
     *
     * Search RETS server
     *
     * @param null $query
     * @param string $searchType
     * @param string $class
     * @param string $queryType
     *
     * @return Collection|null
     */
    public function search($query = null, $searchType = 'Property', $class = 'A', $queryType = 'DMQL2')
    {
        //sample query from http://www.flexmls.com/support/rets/tutorials/example-rets-session/
//        http://retsgw.flexmls.com/rets2_0/Search
//        SearchType=Property&
//        Class=A&
//        QueryType=DMQL2&
//        Query=%28LIST_15=%7COV61GOJ13C0%29&Count=0&Format=COMPACT-DECODED&StandardNames=0&RestrictedIndicator=****&Limit=50

        if (is_null($query)) {
            return null;
        }

        $resources = $this->client->get(
            'Search',
            array(),
            array(
                'query' => array(
                    'SearchType'    => $searchType,
                    'Class'         => $class,
                    'QueryType'     => $queryType,
                    'Query'         => $query,
                    'Count'         => 0,
                    'Format'        => 'COMPACT-DECODED',
                    'StandardNames' => 0,

                )
            )
        );

        $res = $resources->send();
        // store output just in case
        \File::put(app_path() . '/storage/search_' . md5($query) . '.xml', $res->getBody(true));


        $resourcesData = $res->xml();
        if ((string)$resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }
        $result = array();
        // get results
        //$result = (array)$resourcesData->xpath('METADATA/METADATA-TABLE/Field');

        // return collection
        return new Collection($result);

    }


    /**
     *
     * Output last issue
     *
     * @return mixed
     */
    public function getLastError()
    {
        if (is_array($this->lastError)) {
            return $this->lastError[count($this->lastError) - 1];
        }

        return $this->lastError;
    }
}



