<?php namespace App\RedisModels;
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/10
 * Time: 01:40
 */

class RequestedUrlSet extends BaseModel
{
    protected static $key = 'requestedUrlSet';

    protected static $type = 'set';
}