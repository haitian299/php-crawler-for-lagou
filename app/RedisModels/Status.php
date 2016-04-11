<?php namespace App\RedisModels;
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/10
 * Time: 14:54
 */

class Status extends BaseModel
{
    protected static $key = 'status';

    protected static $type = 'string';

    protected static $run = "1";

    protected static $stop = "0";

    public static function isStopped()
    {
        if (static::get() == static::$stop) {
            LogQueue::set("status has been set to stop");

            return true;
        }

        return false;
    }

    public static function run()
    {
        return static::set(static::$run);
    }
}