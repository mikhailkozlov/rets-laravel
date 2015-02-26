<?php namespace Mikhailkozlov\RetsLaravel;


use Illuminate\Database\Eloquent\Model;

class RetsProperty extends Model
{
    protected $primaryKey = 'listingid';
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
    protected $fillable = [];


    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];


    public function getIdAttribute($value)
    {
        return $this->{$this->primaryKey};
    }

    public function getTitleAttribute($value)
    {
        return $this->housenbr.' '.$this->streetname.' '.$this->streetsuff;
    }

    public function getFullAddressAttribute($value)
    {
        return $this->housenbr.' '.$this->streetname.' '.$this->streetsuff.' '.$this->city.', '.$this->state.' '.$this->zip;
    }

    public function getFullStreetAttribute($value)
    {
        return $this->housenbr.' '.$this->streetname.' '.$this->streetsuff;
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
    static public function createFromRaw($raw)
    {
        $attributes = [];
        foreach ($raw as $key => $value) {
            $attributes[$key] = $value;
            if (!is_null(\Config::get('rets.rets_property.' . $key . '.matadata_id', null))) {
                $ids = explode(',', $value);
                $values = RetsField::where('lookup_id',
                    \Config::get('rets.rets_property.' . $key . '.matadata_id', null))
                    ->whereIn('id', $ids)
                    ->remember(20)
                    ->get();

                $value = $values->lists('long', 'id');
                $attributes[$key] = implode(', ', $value);
            }
        }

        return static::create($attributes);
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