<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/10
 * Time: 00:32
 */
use Predis\Client;

class Redis
{
    static protected $clients = array();

    public static function getClient()
    {
        $pid = getmypid();
        if (empty(static::$clients[$pid])) {
            static::$clients[$pid] = new Client(Config::get('database.redis.default'));
        }

        return static::$clients[$pid];
    }
}