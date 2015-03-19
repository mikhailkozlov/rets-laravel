<?php namespace Mikhailkozlov\RetsLaravel\Console;

use Illuminate\Console\Command,
    Illuminate\Support\Collection,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Mikhailkozlov\RetsLaravel\FileLoader,
    Mikhailkozlov\RetsLaravel\RetsField,
    Illuminate\Filesystem\Filesystem,
    Illuminate\Support\Str;


class RetsCommand extends Command
{

    const RESOURCE = 'Property'; // default resource
    const QUALITY  = 'HiRes'; // default image quality

    /**
     * @var Mikhailkozlov\RetsLaravel\Rets\RetsRepository
     */
    public $rets;

    public $retsResources = null;
    public $retsClasses   = null;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct($this->name);

        // We will go ahead and set the name, description, and parameters on console
        // commands just to make things a little easier on the developer. This is
        // so they don't have to all be manually specified in the constructors.
        $this->setDescription($this->description);

        $this->specifyParameters();

        $this->rets = \App::make('rets');
    }

    /**
     *
     * Get available resources from API
     *
     * @return null
     */
    public function getAvailableResources()
    {
        // get top level resources
        $this->retsResources = $this->rets->getResource();

        // check if we got any
        if (is_null($this->retsResources)) {
            $this->error('Unable to load Resource metadata');

            return null;
        }

        return $this->retsResources;
    }


    /**
     *
     * Present list of options an pick resource
     *
     * @return mixed
     */
    public function pickResource()
    {

        if ($this->retsResources == null) {
            $this->getAvailableResources();
        }

        // we're on the roll
        $this->info('Following Resource are available:');

        $default = null;
        // loop and show options
        foreach ($this->retsResources as $i => $resource) {
            // normal things are green
            $line = ' [' . $i . '] ' . $resource->StandardName . ' - ' . $resource->Description;

            // there is 99% chance that we need property
            if (stripos($resource->StandardName, 'property') !== false) {
                $default = $i;
                $line = '<fg=green;options=bold>' . $line . '</fg=green;options=bold>';
            }

            // output
            $this->info($line);
        }

        // get ID for next step
        $selectedResource = $this->ask('What resource would you like to import? [0-9]', $default);

        if (is_null($selectedResource)) {
            return null;
        }

        return $this->retsResources->get($selectedResource);
    }

    /**
     *
     * Get available Property Classes for a resource ID
     *
     * @param $resource_id
     *
     * @return null
     *
     */
    public function getAvailableClasses($resource_id)
    {
        if (is_null($resource_id)) {
            $this->error('Please provide valid resource ID');

            return null;
        }
        $this->retsClasses = $this->rets->getClass($resource_id);

        if (is_null($this->retsClasses)) {
            $this->error('Unable to load Classes metadata for Resource ID: ' . $resource_id);

            return null;

        }

        return $this->retsClasses;
    }

    /**
     *
     * Pick class from list of available Classes for a resource ID
     *
     * @param null $resource_id
     *
     * @return bool|null
     *
     */
    public function pickClasses($resource_id = null)
    {

        if (is_null($this->retsClasses) && !is_null($resource_id)) {
            $this->getAvailableClasses($resource_id);
        }

        if (is_null($this->retsClasses)) {
            $this->error('Classes are not available');

            return null;
        }

        $this->info('Following Classes are available:');
        $choices = [];
        $default = null;
        // loop classes and let pick multiple
        foreach ($this->retsClasses as $i => $class) {

            // normal things are green
            $line = ' [' . $i . '] ' . $class->VisibleName . ' - ' . $class->Description;

            // there is 99% chance that we need property
            if (stripos($class->StandardName, 'property') !== false) {
                $line = '<fg=green;options=bold>' . $line . '</fg=green;options=bold>';
            }

            // output
            $this->info($line);
        }

        // now we need to know what tables to get
        $selectedClasses = $this->ask('What class would you like to import? (0-9 or all)', $default);

        if($selectedClasses == 'all'){
            return $this->retsClasses;
        }

        $selectedClasses = explode(',',$selectedClasses);

        if (empty($selectedClasses)) {

            return null;
        }

        $return = new Collection();
        foreach ($selectedClasses as $selected) {
            $return->push($this->retsClasses->get($selected));
        }

        return $return;
    }

    /**
     * Run setup
     *
     * @author mkozlov
     *
     */
    public function parseFields($table, \Illuminate\Support\Collection $sourceFields)
    {
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

        // we need to write metadata to  config
        $l = new FileLoader(new Filesystem(), app_path() . '/config');
        $l->save(['rets_' . strtolower($table) => $labelMetadata], '', 'rets');

        // create migration
        $this->call('generate:migration',
            array(
                'migrationName' => 'create_rets_' . Str::plural(strtolower($table)) . '_table',
                '--fields'      => implode(', ', $fields)
            ));

        return $metadata;
    }
}