<?php namespace Mikhailkozlov\RetsLaravel;


use Illuminate\Database\Eloquent\Model;

class RetsField extends Model
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
    protected $fillable = ['id', 'resource', 'lookup_id', 'short', 'long'];


    /**
     *
     * Method designed to save XML data into a table
     *
     * @param SimpleXMLElement $fieldData
     *
     * @return \Illuminate\Support\Collection
     */
    public static function createFromXml(\SimpleXMLElement $fieldData)
    {
        $instance = new static;
        if (!is_null($fieldData->attributes())) {
            foreach ($fieldData->attributes() as $k => $v) {
                if ($k == 'Resource') {
                    $instance->resource = $v;
                }
                if ($k == 'Lookup') {
                    $instance->lookup_id = $v;
                }
            }
        }

        $lookup = (array)$fieldData->xpath('Lookup');

        foreach ($lookup as $field) {
            $instance->exists = false;
            $field = (array)$field;
            $instance->id = $field['Value'];
            $instance->long = $field['LongValue'];
            $instance->short = $field['ShortValue'];
            $instance->save();
        }

        return $instance->newQuery()->where('lookup_id',$instance->lookup_id)->where('resource',$instance->resource)->get();
    }
}