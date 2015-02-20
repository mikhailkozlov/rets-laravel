<?php namespace Mikhailkozlov\RetsLaravel\Command;

use Illuminate\Console\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Collection;


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
        $this->line($this->name.' command');
    }


    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
        );
    }



} 