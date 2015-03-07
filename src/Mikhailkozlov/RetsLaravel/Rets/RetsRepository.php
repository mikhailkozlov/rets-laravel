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
        if ((string) $login->xml()->attributes()->ReplyText != 'Success') {
            throw new ConnectionException((string) $login->xml()->attributes()->ReplyText);
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

        if ((string) $resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array) $resourcesData->xpath('METADATA/METADATA-RESOURCE/Resource');

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

        if ((string) $resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array) $resourcesData->xpath('METADATA/METADATA-CLASS/Class');

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
        if ((string) $resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array) $resourcesData->xpath('METADATA/METADATA-TABLE/Field');

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
     * @return SimpleXMLElement|null
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
        if ((string) $resourcesData->attributes()->ReplyText != 'Success') {
            $this->lastError = $resourcesData->attributes()->ReplyText;

            return null;
        }

        // get results
        $result = (array) $resourcesData->xpath('METADATA/METADATA-LOOKUP_TYPE');

        return $result;
    }

    /**
     *
     * http://retsgw.flexmls.com/rets2_1/GetObject?Type=Photo&Resource=Property&ID=20080112084722814782000000:*
     *
     * @param $ResourceID         - Resource ID ex: Property
     * @param $internalID         - internal MLS system property id, it is not the same as MLS Number in most cases
     * @param string $imageNumber - default is * - all images, you can pass 0 to get main image or any number of the image you need
     *
     * @return null|array|Collection
     */
    public function getImage($ResourceID, $internalID, $imageNumber = '*')
    {
        if (is_null($internalID)) {
            return null;
        }

        $result = null;

        $resources = $this->client->get(
            'GetObject',
            array(),
            array(
                'query'   => array(
                    'Type'     => 'Photo',
                    'Resource' => $ResourceID,
                    'ID'       => $internalID . ':' . $imageNumber,
                ),
                'save_to' => app_path() . '/storage/photo_' . $ResourceID . '_' . $internalID . '_' . $imageNumber . '.txt'
            )
        );

        $res = $resources->send();
        echo $res->getHeader('Content-Type');
        if ($res->isContentType('multipart/parallel')) {
            echo 'going to parse multy';
            // we have multi part body and we need to parse it

            // get boundary
            $boundary = explode('boundary=', $res->getHeader('Content-Type'));
            $boundary = trim($boundary[1], '"');

            // split into files
            $files = explode('--' . $boundary, $res->getBody(true));

            // strip first and last item
            if (count($files) > 3) {
                $files = array_slice($files, 1, (count($files) - 2));
            }

            if (!empty($files)) {
                // we have multi part body and we need to parse it
                $result = new Collection();
            }

            // loop over files
            foreach ($files as $k => $f) {
                // parse what we have
                $parsed = $this->parseMultiPartMessage($f);

                if (empty($parsed['headers'])) {
                    // looks like empty line, skip
                    continue;
                }

                $result->push($parsed);
            }

            return $result;
        } elseif ($res->isContentType('image')) {

            // we have a single image, deal with that
            return array(
                'headers'   => $res->getHeaders()->toArray(),
                'file'      => $res->getBody(true),
                'extension' => $this->getExtensionFromContentType($res->getHeader('Content-Type')),
            );
        }

        return $result;
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
     * @return SimpleXml|null
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
                'query'   => array(
                    'SearchType'    => $searchType,
                    'Class'         => $class,
                    'QueryType'     => $queryType,
                    'Query'         => $query,
                    'Count'         => 0,
                    'Format'        => 'COMPACT-DECODED',
                    'StandardNames' => 0,

                ),
                'save_to' => app_path() . '/storage/search_' . md5($query) . '.xml'
            )
        );

        $res = $resources->send()->xml();

        if ((string) $res->attributes()->ReplyText != 'Success') {
            $this->lastError = $res->attributes()->ReplyText;

            return null;
        }

        return $res;
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


    /**
     * Parse a message into parts
     *
     * @param string $message Message to parse
     *
     * @return array
     */
    protected function parseMultiPartMessage($message)
    {
        $message = trim($message);
        $headers = array();
        $file = '';
        $extension = '';

        // Iterate over each line in the message, accounting for line endings
        $lines = preg_split('/(\\r?\\n)/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0, $totalLines = count($lines); $i < $totalLines; $i += 2) {

            $line = $lines[$i];

            // If two line breaks were encountered, then this is the end of body
            if (empty($line)) {
                if ($i < $totalLines - 1) {
                    $file = implode('', array_slice($lines, $i + 2));
                }
                break;
            }

            // Parse message headers
            if (strpos($line, ':')) {
                $parts = explode(':', $line, 2);
                $key = trim($parts[0]);
                $value = isset($parts[1]) ? trim($parts[1]) : '';
                if (!isset($headers[$key])) {
                    $headers[$key] = $value;
                } elseif (!is_array($headers[$key])) {
                    $headers[$key] = array($headers[$key], $value);
                } else {
                    $headers[$key][] = $value;
                }
            }
        }

        // get file extension from content type
        if (array_key_exists('Content-Type', $headers)) {
            $extension = $this->getExtensionFromContentType($headers['Content-Type']);
        }


        return array(
            'headers'   => $headers,
            'file'      => $file,
            'extension' => $extension
        );
    }

    /**
     * @param $type - expected something like image/jpeg
     *
     * @return null|string
     *
     */
    protected function getExtensionFromContentType($type)
    {
        if (is_array($type)) {
            echo "\n";
            print_r($type);
            echo "\n";

            return 'jpg';
        }
        $type = explode('/', $type);
        if (array_key_exists(1, $type)) {
            return $type[1];
        }

        return null;
    }
}



