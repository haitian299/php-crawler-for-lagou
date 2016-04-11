<?php namespace App\RedisModels;
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 10:58
 */

class ParsedJobSet extends BaseModel
{
    protected static $key = 'parsedJobSet';

    protected static $type = 'set';
}