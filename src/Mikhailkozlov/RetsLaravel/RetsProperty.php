<?php namespace Mikhailkozlov\RetsLaravel;


use Jenssegers\Mongodb\Model as Eloquent;

class RetsProperty extends Eloquent
{
    protected $collection = 'rets_properties';
    protected $connection = 'mongodb';
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'listingid';

    protected $guarded = [];


    public function photos(){

        return $this->embedsMany('Mikhailkozlov\\RetsLaravel\\RetsImage');
    }

    public function getIdAttribute($value)
    {
        return $this->{$this->primaryKey};
    }

    public function getTitleAttribute($value)
    {
        return $this->housenbr . ' ' . $this->streetname . ' ' . $this->streetsuff;
    }

    public function getFullAddressAttribute($value)
    {
        return $this->housenbr . ' ' . $this->streetname . ' ' . $this->streetsuff . ' ' . $this->city . ', ' . $this->state . ' ' . $this->zip;
    }

    public function getFullStreetAttribute($value)
    {
        return $this->housenbr . ' ' . $this->streetname . ' ' . $this->streetsuff;
    }


    public function getDescriptionAttribute($value)
    {
        return $this->remark1;
    }


    /**
     *
     * Create new entry from data from API
     *
     * @param $raw
     *
     * @return static
     */
    static public function createFromRaw($raw, $table = 'rets_class_a')
    {
        $attributes = [];
        foreach ($raw as $key => $value) {
            $attributes[$key] = $value;
            $metadata_id = \Config::get('rets.' . $table . '.' . $key . '.matadata_id', null);
            if (!is_null($metadata_id)) {
                $ids = explode(',', $value);
                $values = RetsField::where('lookup_id', $metadata_id)
                    ->whereIn('id', $ids)
                    ->remember(20)
                    ->get();
                if (!$values->isEmpty()) {
                    $valueFromMap = $values->lists('long', 'id');
                    $attributes[$key] = implode(', ', $valueFromMap);
                } else {
                    $attributes[$key] = $value;
                }
            }
        }

        $model = new static($attributes);

        $model->save();

        return $model;
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string $key
     *
     * @return mixed
     */
    protected function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        elseif (in_array($key, $this->getDates())) {
            if ($value) {
                return $this->asDateTime($value);
            }
        } elseif (!is_null(\Config::get('rets.rets_property.' . $key . '.matadata_id', null))) {
            $ids = explode(',', $value);
            $values = RetsField::where('lookup_id', \Config::get('rets.rets_property.' . $key . '.matadata_id', null))
                ->whereIn('id', $ids)
                //->remember(20)
                ->get();

            if ($values->isEmpty()) {
                return $value;
            }

            return $values;
        }

        return $value;
    }

}