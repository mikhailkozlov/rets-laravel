<?php namespace Mikhailkozlov\RetsLaravel;


use Jenssegers\Mongodb\Model as Eloquent;

/**
 *
 * Class RetsImage
 * @package Mikhailkozlov\RetsLaravel
 *
 * Class to store images in DB and on the file system
 *
 */
class RetsImage extends Eloquent
{
    protected $collection = 'rets_photos';
    protected $connection = 'mongodb';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'path',
        'description',
        'default',
        'position',
        'type',
        'size',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

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
                $attributes['position'] = intval($fileData['headers']['Object-ID']);
            }
            if (array_key_exists('Location', $fileData['headers'])) {
                $attributes['path'] = $fileData['headers']['Location'];
            }
        }
        if (array_key_exists('extension', $fileData)) {
            $attributes['name'] .= '.' . $fileData['extension'];
        }

        if(!empty($attributes['path'])){
            $parts = explode('/', $attributes['path']);
            $attributes['name'] = end($parts);
        }

        $model = new static($attributes);

        return $model;
    }
}