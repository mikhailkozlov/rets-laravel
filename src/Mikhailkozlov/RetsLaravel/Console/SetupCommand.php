<?php namespace Mikhailkozlov\RetsLaravel\Console;

use GuzzleHttp\Collection;
use Illuminate\Console\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Mikhailkozlov\RetsLaravel\FileLoader,
    Illuminate\Filesystem\Filesystem;


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
    public function parseFields($table, \Illuminate\Support\Collection $sourceFields)
    {
        $fields = [];
        $usedFieldNames = [];
        $labelMetadata = [];

        foreach ($sourceFields as $i => $sourceField) {
            // working with array is simple
            $sourceField = (array)$sourceField;

            // set default name
            $field = [(string)$sourceField['DBName']];

            // make sure we have DB name from the system, in case it is missing we're going to use system name
            if (empty($field[0]) || in_array($field[0], $usedFieldNames)) {
                $field[0] = strtolower((string)$sourceField['SystemName']);
            }

            // make sure we do not have dupes
            $usedFieldNames[] = $field[0];

            $sourceField['MaximumLength'] = intval($sourceField['MaximumLength']);

            switch ($sourceField['DataType']) {
                case 'Int':
                    $field[] = 'integer';
                    break;
                case 'DateTime':
                    $field[] = 'dateTime';
                    break;
                case 'Date':
                    $field[] = 'date';
                    break;
                case 'Decimal':
                    $field[] = 'decimal(10,4)';
                    break;
                case 'Character':
                default:
                    if ($sourceField['MaximumLength'] < 250) {
                        $field[] = 'string(' . $sourceField['MaximumLength'] . ')';
                    } else {
                        $field[] = 'text';
                    }
                    break;
            }
            if (intval($sourceField['Required']) == 0) {
                $field[] = 'nullable';
            }
            if (intval($sourceField['Unique']) == 1) {
                $field[] = 'unique';
            }

            // meta
            $labelMetadata[$field[0]] = [
                'name'       => (string)$sourceField['LongName'],
                'type'       => (string)$sourceField['DataType'],
                'searchable' => intval($sourceField['Searchable']),
            ];
            // store
            $fields[] = implode(':', $field);
        }

        // we need to write metadata to  config
        $l = new FileLoader(new Filesystem(), app_path() . '/config');
        $l->save(['rets_' . strtolower($table) => $labelMetadata], '', 'rets');

        // create migration
        $this->call('generate:migration',
            array(
                'migrationName' => 'create_rets_' . strtolower($table) . '_table',
                '--fields'      => implode(', ', $fields)
            ));

        // we can move default model

        // we need default controllers
        $runMigration = $this->ask('Would you like to run migration now? (y/n)');
        if (in_array($runMigration, array('y', 'yes'))) {
            $this->call('migrate', array('--env' => $this->option('env')));
        }
    }

    public function fire()
    {
        // get connector
        $rets = \App::make('rets');

        // get top level resources
        $metaResource = $rets->getResource();

        // check if we got any
        if (is_null($metaResource)) {
            $this->error('Unable to load Resource metadata');

            return;
        }

        // we're on the roll
        $this->info('Following Resource are available:');

        // loop and show options
        foreach ($metaResource as $i => $resource) {
            // normal things are green
            $line = ' [' . $i . '] ' . $resource->StandardName . ' - ' . $resource->Description;

            // there is 99% chance that we need property
            if (stripos($resource->StandardName, 'property') !== false) {
                $line = '<fg=green;options=bold>' . $line . '</fg=green;options=bold>';
            }

            // output
            $this->info($line);
        }

        // get ID for next step
        $selectedResource = $this->ask('What resource would you like to import? [0-9]');
        $this->info('Retrieving resource data for ' . $selectedResource);

        // get meta Classes for Resource
        $metaClass = $rets->getClass($metaResource->get($selectedResource)->ResourceID);

        // make sure we got any
        if(is_null($metaClass)){
            $this->error('Unable to load Class metadata with error ' . $rets->getLastError());

            return;
        }

        $this->info('Following Classes are available:');

        // loop classes and let pick multiple
        foreach ($metaClass as $i => $resource) {
            // normal things are green
            $line = ' [' . $i . '] ' . $resource->VisibleName . ' - ' . $resource->Description;

            // there is 99% chance that we need property
            if (stripos($resource->StandardName, 'property') !== false) {
                $line = '<fg=green;options=bold>' . $line . '</fg=green;options=bold>';
            }
            $this->info($line);
        }

        // now we need to know what tables to get
        $selectedClass = $this->ask('What class would you like to import? [0-9]');
        $this->info('Retrieving class data for ' . $selectedClass);

        // get an array
        $selectedClass = explode(',', $selectedClass);
        // loop over selection and get table
        foreach ($selectedClass as $classId) {
            // pull meta for table
            $metaTable = $rets->getTable(
                $metaResource->get($selectedResource)->ResourceID,
                $metaClass->get($classId)->ClassName
            );

            // time to create date
            //$this->parseFields($metaResource->get($selectedResource)->StandardName, $metaTable);
        }

    }


    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
        );
    }

} 