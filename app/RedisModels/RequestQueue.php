<?php namespace App\RedisModels;
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/10
 * Time: 01:38
 */

class RequestQueue extends BaseModel
{
    protected static $key = 'requestQueue';

    protected static $type = 'list';
}