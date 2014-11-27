<?php namespace Mikhailkozlov\RetsLaravel;

use Illuminate\Console\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument;


class SetupCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rets:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup RETS DB and other things';

    /**
     * Run setup
     *
     * @author mkozlov
     *
     */
    public function fire()
    {
        $rets = \App::make('rets');

        $metaResource = $rets->getResource();

        if (is_null($metaResource)) {
            $this->error('Unable to load Resource metadata');

            return;
        }
        $this->info('Following Resource are available:');
        foreach ($metaResource as $i => $resource) {
            $line = ' [' . $i . '] ' . $resource->StandardName . ' - ' . $resource->Description;
            if (stripos($resource->StandardName, 'property') !== false) {
                $line = '<options=bold>'.$line . '</options=bold>';
            }
            $this->info($line);
        }
        $selectedResource = $this->ask('What resource would you like to import? [0-9]');
        $this->info('Retrieving resource data for ' . $selectedResource);

    }


    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
        );
    }

} 