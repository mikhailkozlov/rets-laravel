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
        $resourcesData = $resources->send()->xml();
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
        if(is_null($classID)){
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
        $resourcesData = $resources->send()->xml();
        if ((string)$resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array)$resourcesData->xpath('METADATA/METADATA-CLASS/Class');

        // return collection
        return new Collection($result);
    }

    public function getTable($ResourceID, $classID)
    {
        if(is_null($classID)){
            return null;
        }

        $resources = $this->client->get(
            'GetMetadata',
            array(),
            array(
                'query' => array(
                    'Type' => 'METADATA-TABLE',
                    'ID'   => $ResourceID.':'.$classID,
                )
            )
        );
        $resourcesData = $resources->send()->xml();
        if ((string)$resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // store output just in case
        \File::put(app_path().'/storage/'.$ResourceID.'_'.$classID.'.xml', $resourcesData->__toString());

        // get results
        $result = (array)$resourcesData->xpath('METADATA/METADATA-TABLE/Field');

        // return collection
        return new Collection($result);
    }


}



