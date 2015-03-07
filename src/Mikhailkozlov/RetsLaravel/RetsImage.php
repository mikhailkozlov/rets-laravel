<?php namespace Mikhailkozlov\RetsLaravel;


use Illuminate\Database\Eloquent\Model,
    League\Flysystem\Filesystem;

/**
 *
 * Class RetsImage
 * @package Mikhailkozlov\RetsLaravel
 *
 * Class to store images in DB and on the file system
 *
 */
class RetsImage extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'path',
        'description',
        'default',
        'position',
        'type',
        'size',
        'parent_type',
        'parent_id'
    ];

    /**
     * @var League\Flysystem\Filesystem
     */
    protected $filemanager;

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        $this->filemanager = \App::make('rets.storage');
    }


    /**
     * Save a new model and return the instance.
     *
     * @param  array $attributes
     *
     * @return static
     */
    public static function fromApi(array $fileData)
    {
        // we need to parse attributes
        $attributes = ['name' => ''];

        // we must have headers, file (the data), and extension
        if (array_key_exists('headers', $fileData)) {
            $attributes['name'] = $fileData['headers']['Content-ID'];

            if (array_key_exists('Content-Type', $fileData['headers'])) {
                $attributes['type'] = $fileData['headers']['Content-Type'];
            }
            if (array_key_exists('Content-Description', $fileData['headers'])) {
                $attributes['description'] = $fileData['headers']['Content-Description'];
            }
            if (array_key_exists('Preferred', $fileData['headers'])) {
                $attributes['default'] = $fileData['headers']['Preferred'];
            }
            if (array_key_exists('Object-ID', $fileData['headers'])) {
                $attributes['position'] = $fileData['headers']['Object-ID'];
            }
        }
        if (array_key_exists('extension', $fileData)) {
            $attributes['name'] .= '.' . $fileData['extension'];
        }
        $model = new static($attributes);

        return $model;
    }


    public function write($file)
    {
        if (empty($this->name)) {
            return false;
        }

        if (empty($this->parent_id)) {
            return false;
        }

        $meta = $this->filemanager->put($this->parent_id . '/' . $this->name, $file);

        echo 'File Meta:' . "\n";
        print_r($meta);
        echo "\n";

        if (is_array($meta)) {
            $this->path = $meta['path'];
            $this->size = $meta['size'];
        }

        return $this;
    }

    /**
     * Get file system provider
     *
     * @return mixed
     */
    public function getFilemanager()
    {
        return $this->filemanager;
    }

}