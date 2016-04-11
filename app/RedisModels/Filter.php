<?php namespace App\RedisModels;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 17:20
 */
class Filter extends BaseModel
{
    public static function fget($key)
    {
        return static::redisClient()->smembers($key);
    }

    public static function fset($key, $value)
    {
        if (empty($value)) {
            throw new \Exception("try to set filter {$key} empty array");
        }
        LogQueue::set("saved filter {$key}");
        if (static::flength($key) == 0) {
            return static::redisClient()->sadd($key, $value);
        }
    }

    public static function flength($key)
    {
        return static::redisClient()->scard($key);
    }
}