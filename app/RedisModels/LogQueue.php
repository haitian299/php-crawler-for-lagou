<?php namespace App\RedisModels;
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/10
 * Time: 14:48
 */

class LogQueue extends BaseModel
{
    protected static $key = 'logQueue';

    protected static $type = 'list';

    public static function set($value)
    {
        echo $value."\n";
        return parent::set($value);
    }
}