<?php namespace App\DatabaseModels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 15:12
 */
abstract class BaseModel extends Model
{
    public static function firstOrNew(array $attributes)
    {
        if (!is_null($instance = static::where($attributes)->first())) {
            return $instance;
        }

        return new static($attributes);
    }

    public static function updateOrCreate(array $attributes, array $values = array())
    {
        Capsule::connection()->reconnect();

        $instance = static::firstOrNew($attributes);

        $instance->fill($values)->save();

        return $instance;
    }
}