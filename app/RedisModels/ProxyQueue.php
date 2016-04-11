<?php namespace App\RedisModels;

use App\Config;
use GuzzleHttp\Client as Guzzle;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/10
 * Time: 01:42
 */
class ProxyQueue extends BaseModel
{
    protected static $key = 'proxyQueue';

    protected static $type = 'list';

    public static function load()
    {
        $proxyApi = Config::get('downloader.proxyApi');
        if (!empty($proxyApi)) {
            $guzzle = new Guzzle();
            $response = $guzzle->request('GET', $proxyApi);
            $content = $response->getBody()->getContents();
            $jsonContent = json_decode($content, true);
            $proxyArray = $jsonContent['result'];
            $proxies = [];
            foreach ($proxyArray as $proxy) {
                $proxies[] = 'tcp://' . $proxy['ip:port'];
            }
            if (!empty($proxies)) {
                LogQueue::set("new proxies loaded");
                static::set($proxies);
            }
        } else {
            throw new \Exception("please set proxy api if you want to use proxy");
        }
    }

    public static function get()
    {
        if (static::length() < Config::get('downloader.proxyMinimumCount')) {
            static::load();
        }

        return parent::get();
    }
}