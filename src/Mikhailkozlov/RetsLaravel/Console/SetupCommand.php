<?php namespace Mikhailkozlov\RetsLaravel\Console;

use Illuminate\Console\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Mikhailkozlov\RetsLaravel\FileLoader,
    Mikhailkozlov\RetsLaravel\RetsField,
    Illuminate\Filesystem\Filesystem,
    Illuminate\Support\Str;


class SetupCommand extends RetsCommand
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
     *
     */
    public function fire()
    {

        // get ID for next step
        $selectedResource = $this->pickResource();
        $this->info('Retrieving resource data for ' . $selectedResource->ResourceID);

        // get meta Classes for Resource
        // now we need to know what tables to get
        $selectedClasses = $this->pickClasses($selectedResource->ResourceID);

        // we're going to get metadata to retrieve
        $fieldMetadata = [];
        // loop over selection and get table
        foreach ($selectedClasses as $class) {
            // pull meta for table
            $metaTable = $this->rets->getTable(
                $selectedResource->ResourceID,
                (string) $class->ClassName
            );

            // time to create date
            $fieldMetadata = array_merge(
                $fieldMetadata,
                $this->parseFields('class_' . (string) $class->ClassName, $metaTable)
            );
        }

        // pull metadata
        if (!empty($fieldMetadata)) {

            $this->info('We need to pull data for ' . count($fieldMetadata) . ' ' . \Str::plural('field',
                    count($fieldMetadata)));

            foreach ($fieldMetadata as $meta_id => $id) {
                $fieldData = $this->rets->getFieldMetadata($selectedResource->ResourceID, $meta_id);
                try {
                    RetsField::createFromXml($fieldData[0]);
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
        } else {
            $this->info('Looks like we not able to find a single field that requires metadata look up. Skipping.');

        }

        // we need default controllers
        $runMigration = $this->ask('Would you like to run migration now? (y/n)');
        if (in_array($runMigration, array('y', 'yes'))) {
            $this->call('migrate', array('--env' => $this->option('env')));
        }
    }


    public function getOptions()
    {
        return array(
            array('env', null, InputOption::VALUE_OPTIONAL, 'The environment the command should run under.', null),
        );
    }


    /**
     * Run setup
     *
     * @author mkozlov
     *
     */
    public function parseFields($table, \Illuminate\Support\Collection $sourceFields)
    {
        $table = strtolower($table);
        $fields = []; // fields used in schema generator
        $usedFieldNames = []; //
        $labelMetadata = []; // data for config file with links to $metadata
        $metadata = []; // array of IDs we need to look for in metadata call

        foreach ($sourceFields as $i => $sourceField) {
            // working with array is simple
            $sourceField = (array) $sourceField;

            // set default name
            $field = [(string) $sourceField['DBName']];

            // make sure we have DB name from the system, in case it is missing we're going to use system name
            if (array_key_exists(0, $field) && empty($field[0]) || in_array($field[0], $usedFieldNames)) {
                $field[0] = strtolower((string) $sourceField['SystemName']);
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
                'long'        => (string) $sourceField['LongName'],
                'type'        => (string) $sourceField['DataType'],
                'searchable'  => intval($sourceField['Searchable']),
                'name'        => $sourceField['SystemName'],
                'dbname'      => $field[0],
                'matadata_id' => null,
                'multiple'    => false,
            ];

            // push metadata to array
            if (array_key_exists('Interpretation',
                    $sourceField) && strtolower($sourceField['Interpretation']) == 'lookup'
            ) {
                $sourceField['LookupName'] = trim($sourceField['LookupName']);
                $metadata[$sourceField['LookupName']] = $sourceField['LookupName'];
                $labelMetadata[$field[0]]['matadata_id'] = $sourceField['LookupName'];
            }

            // push metadata to array
            if (array_key_exists('Interpretation',
                    $sourceField) && strtolower($sourceField['Interpretation']) == 'lookupmulti'
            ) {
                $sourceField['LookupName'] = trim($sourceField['LookupName']);
                $metadata[$sourceField['LookupName']] = $sourceField['LookupName'];
                $labelMetadata[$field[0]]['matadata_id'] = $sourceField['LookupName'];
                $labelMetadata[$field[0]]['multiple'] = true;
            }

            // store
            $fields[] = implode(':', $field);
        }

        $updateConfig = $this->ask('Would you like to update config? (y/n)', 'y');
        if (in_array($updateConfig, array('y', 'yes'))) {
            // we need to write metadata to  config
            $l = new FileLoader(new Filesystem(), app_path() . '/config');

            // get current config
            $config = \Config::get('rets', []);

            // add new values
            $config['rets_' . $table] = $labelMetadata;

            // out
            $this->line('Going to write rets_' . $table . ' to config');

            // save combined config
            $l->save($config, '', 'rets');

            // set current config as file will not be reloaded
            \Config::set('rets', $config);
        }
        // we need default controllers
        $runMigration = $this->ask('Would you like to create migration for ' . $table . '_table? (y/n)', 'y');
        if (in_array($runMigration, array('y', 'yes'))) {
            // create migration
            $this->call('generate:migration',
                array(
                    'migrationName' => 'create_rets_' . $table . '_table',
                    '--fields'      => implode(', ', $fields)
                ));
        }

        return $metadata;
    }


} 