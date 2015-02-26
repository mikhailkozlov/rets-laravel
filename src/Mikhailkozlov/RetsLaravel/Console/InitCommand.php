<?php namespace Mikhailkozlov\RetsLaravel\Console;

use Illuminate\Console\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Mikhailkozlov\RetsLaravel\RetsProperty,
    Mikhailkozlov\RetsLaravel\RetsImage;
use Illuminate\Support\Collection;


class InitCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rets:init';


    protected $rets;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all properties from RETS DB, load images for all properties';

    /**
     * Run update
     *
     * @author mkozlov
     *
     */
    public function fire()
    {
        $this->rets = \App::make('rets');

        if ($this->option('data') === true) {
            $this->importData();
        }
        if ($this->option('images') === true) {
            $this->importImages();
        }

    }


    protected function importData()
    {
        // get config
        $retsMeta = new Collection(\Config::get('rets.rets_property', []));
        $retsMeta = $retsMeta->lists('dbname', 'name');

        // get connector

        // get top level resources
        $xml = (array) $this->rets->search('(LIST_87=1950-01-01T00:00:00+)');

        if(!array_key_exists('COLUMNS', $xml)){
            $this->error('COLUMNS as missing. This is not normal. Exit');
            return;
        }

        // get columns
        $columns = explode("\t", (string)$xml['COLUMNS']);
        $columnsParsed = [];

        if(count($columns) < 2){
            $this->error('We only see few columns in response from RETS server. This is not normal. Exit');
            return;
        }

        // parse columns and match them with DB name
        foreach ($columns as $column) {
            if (array_key_exists($column, $retsMeta)) {
                $columnsParsed[] = $retsMeta[$column];
            } else {
                $columnsParsed[] = '';
            }
        }

        if(count($xml['DATA']) < 2){
            $this->error('We only see few properties in response from RETS server. This is not normal. Exit');
            return;
        }

        // time to parse data
        foreach ($xml['DATA'] as $line) {
            // get line and split by tab
            $line = explode("\t", $line);

            // match data with columns
            $res = array_combine($columnsParsed, $line);

            // remove empty things
            unset($res['']);

            // create new listing
            RetsProperty::createFromRaw($res);
        }
    }

    protected function importImages()
    {
        // we should have things in DB now, and we can look at that data.
        $listingCount = RetsProperty::count();
        $this->line('We have ' . RetsProperty::count() . ' items in property table');
        $loadImages = $this->ask('Are you ready to load all images? (y/n)','y');
        if (strtolower($loadImages) == 'n') {
            $this->line('You can load images any time later.');
            exit;
        }

        if ($listingCount > 0) {
            for ($i = 0; $i < $listingCount; $i += 100) {
                $repo = new RetsProperty;
                $listings = RetsProperty::take(100)->skip($i)->get([$repo->getKeyName(), 'techid']);
                foreach($listings as $listing){

                    $images = $this->rets->getImage('Property', $this->techid);
                    if(is_null($images)){
                        $this->error($listing->techid. ' has not images');
                        continue;
                    }
                    foreach ($images as $image) {
                        $file = RetsImage::fromApi($image);
                        $file->write($image['file']);
                        $file->save();
                    }
                }
                $this->line($listings->count());

            }
        }
    }

    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
            array('data', null, InputOption::VALUE_OPTIONAL, 'Skip loading data if set to false.', true),
            array('images', null, InputOption::VALUE_OPTIONAL, 'Skip loading data if set to false.', true),
        );
    }
}