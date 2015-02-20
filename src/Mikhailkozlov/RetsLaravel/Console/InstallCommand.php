<?php namespace Mikhailkozlov\RetsLaravel\Console;

use Illuminate\Console\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument;


class InstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rets:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'publish assets,configs and run migration';

    /**
     * Run install
     *
     * @author mkozlov
     *
     */
    public function fire()
    {
        $this->call('migrate', array('--env' => $this->option('env'), '--package' => 'mikhailkozlov/rets-laravel' ) );
        $this->call('config:publish', array('package' => 'mikhailkozlov/rets-laravel' ) );
        $this->call('asset:publish', array('package' => 'mikhailkozlov/rets-laravel' ) );

        if ($this->confirm('Do you wish to setup local database? [yes|no]'))
        {
            $this->call('rets:setup');
        }
    }


    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
        );
    }

}