<?php namespace Mikhailkozlov\RetsLaravel\Rets;

use Aws\CloudFront\Exception\Exception;
use Guzzle\Http\Client,
    Illuminate\Events\Dispatcher,
    Illuminate\Config\Repository,
    Mikhailkozlov\RetsLaravel\Rets\Exceptions\ConnectionException;


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
        print_r($login->getBody(true));
        if ($login->getStatusCode() != 200) {
            throw new ConnectionException((string)$login->xml()->attributes()->ReplyText);
        }
    }

    public function getResource($resourceID = 0)
    {
        // TODO: Implement getResource() method.
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
        echo '<pre>'; // MK: delete me
        print_r((array)$resourcesData);
        echo '</pre>';
    }

    public function getClass($classID = null)
    {
        // TODO: Implement getClass() method.
    }

    public function getTable($classID = null, $type)
    {
        // TODO: Implement getTable() method.
    }


}



