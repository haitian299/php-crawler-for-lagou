<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/25
 * Time: 下午2:32
 */
class Config
{

    public static function get($key)
    {
        if (!empty($key)) {
            $keyArray = explode('.', $key);

            $file = $keyArray[0];
            $keyArray = array_slice($keyArray, 1);

            $result = require __DIR__ . "/../config/{$file}.php";
            foreach ($keyArray as $k) {
                $result = $result[$k];
            }

            return $result;
        }

        return null;
    }
}