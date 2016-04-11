<?php namespace App\Parsers;
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 00:35
 */

interface BaseParser
{
    public static function parse($url, $content);
}