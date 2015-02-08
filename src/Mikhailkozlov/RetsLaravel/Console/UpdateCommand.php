<?php namespace Mikhailkozlov\RetsLaravel;

use Illuminate\Console\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument;


class UpdateCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rets:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update RETS DB to remove outdated data and add new properties';

    /**
     * Run update
     *
     * @author mkozlov
     *
     */
    public function fire()
    {

//        // get connector
//        $rets = \App::make('rets');
//
//        // get top level resources
//        $metaResource = $rets->search('(LIST_87=1950-01-01T00:00:00+)');
        if(!file_exists(app_path() . '/storage/search_9db401aedc9498e1092d8d0f1ae164cb.xml')){
            $this->error('missing cache');
        }


        $xml = (array) simplexml_load_file(app_path() . '/storage/search_9db401aedc9498e1092d8d0f1ae164cb.xml');

        $columns = explode("\t",$xml['COLUMNS']);
        print_r($columns);

        foreach($xml['DATA'] as $line){
            $line = explode("\t",$line);

            $res = array_combine($columns, $line);

            print_r($res);
            die;
        }
    }


    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
        );
    }

} 