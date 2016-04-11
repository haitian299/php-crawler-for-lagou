<?php namespace App\RedisModels;

use App\Config;

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
        $showBy = Config::get('log.showLogBy');
        if ($showBy == 'console' || $showBy == 'both') {
            echo $value . "\n";
        }
        if ($showBy == 'redis' || $showBy == 'both') {
            static::redisClient()->ltrim(static::$key, 0, Config::get('log.maxLogCount'));

            return parent::set($value);
        }
    }
}