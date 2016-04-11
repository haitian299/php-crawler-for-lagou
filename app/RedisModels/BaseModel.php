<?php namespace App\RedisModels;

use App\Redis;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/10
 * Time: 00:27
 */
abstract class BaseModel
{
    protected static $key;

    protected static $type;

    protected static function redisClient()
    {
        return Redis::getClient();
    }

    public static function get()
    {
        switch (static::$type) {
            case 'list':
                return static::redisClient()->rpop(static::$key);
            case 'string':
                return static::redisClient()->get(static::$key);
            default:
                throw new \Exception("type " . static::$type . " does not support get method");
        }
    }

    public static function set($value)
    {
        switch (static::$type) {
            case 'list':
                return static::redisClient()->lpush(static::$key, $value);
            case 'string':
                return static::redisClient()->set(static::$key, $value);
            case 'set':
                return static::redisClient()->sadd(static::$key, $value);
            default:
                throw new \Exception("type " . static::$type . "does not support set method");
        }
    }

    public static function has($value)
    {
        if (static::$type == 'set') {
            return static::redisClient()->sismember(static::$key, $value);
        } else {
            throw new \Exception("type " . static::$type . "does not support has method");
        }
    }

    public static function length()
    {
        switch (static::$type) {
            case 'list':
                return static::redisClient()->llen(static::$key);
            case 'set':
                return static::redisClient()->scard(static::$key);
            default:
                throw new \Exception("type " . static::$type . "does not support length method");
        }
    }

    public static function exist()
    {
        return static::redisClient()->exists(static::$key);
    }
}