<?php namespace Mikhailkozlov\RetsLaravel\Console;

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
    public function parseFields($table, $xml)
    {
//        $xml = simplexml_load_file(app_path() . '/storage/Property_A.xml');
        $sourceFields = $xml->xpath('METADATA/METADATA-TABLE/Field');

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
        $l->save(['rets_'.strtolower($table) => $labelMetadata], '', 'rets');

        // create migration
        $this->call('generate:migration',
            array('migrationName' => 'create_rets_'.strtolower($table).'_table', '--fields' => implode(', ', $fields)));

        // we can move default model

        // we need default controllers

    }

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
                $line = '<fg=green;options=bold>' . $line . '</fg=green;options=bold>';
            }
            $this->info($line);
        }

        // get ID for next step
        $selectedResource = $this->ask('What resource would you like to import? [0-9]');
        $this->info('Retrieving resource data for ' . $selectedResource);

        $metaClass = $rets->getClass($metaResource->get($selectedResource)->ResourceID);

        $this->info('Following Classes are available:');
        foreach ($metaClass as $i => $resource) {
            $line = ' [' . $i . '] ' . $resource->VisibleName . ' - ' . $resource->Description;
            if (stripos($resource->StandardName, 'property') !== false) {
                $line = '<fg=green;options=bold>' . $line . '</fg=green;options=bold>';
            }
            $this->info($line);
        }

        // get ID for next step
        $selectedClass = $this->ask('What class would you like to import? [0-9]');
        $this->info('Retrieving class data for ' . $selectedClass);

        // get an array
        $selectedClass = explode(',', $selectedClass);
        foreach ($selectedClass as $classId) {
            $metaTable = $rets->getTable(
                $metaResource->get($selectedResource)->ResourceID,
                $metaClass->get($classId)->ClassName
            );

//            \File::put(app_path() . '/' . $selectedResource . '_' . $classId . '.txt', var_export($metaTable));
//            print_r($metaTable);

            // time to create date
            $this->parseFields($metaResource->get($selectedResource)->StandardName, $metaTable);
        }

    }


    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
        );
    }

} 