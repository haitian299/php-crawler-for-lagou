<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/9
 * Time: 23:27
 */
use App\RedisModels\RequestedUrlSet;
use GuzzleHttp\Client as Guzzle;
use App\RedisModels\ProxyQueue;

class Downloader
{
    protected static function getOptions()
    {
        $options = Config::get('downloader.requestOptions');
        $options['headers']['User-Agent'] = static::getRandomUserAgent();
        if (Config::get('downloader.useProxy')) {
            $options['proxy'] = static::getProxy();
        }
        $options['connect_timeout'] = Config::get('downloader.connectTimeOut');
        $options['timeout'] = Config::get('downloader.responseTimeOut');

        return $options;
    }

    protected static function getProxy()
    {
        return ProxyQueue::get();
    }

    protected static function getRandomUserAgent()
    {
        $userAgents = Config::get('downloader.userAgents');

        return $userAgents[array_rand($userAgents)];
    }

    public static function getUrlContent($url, $options = null)
    {
        if (empty($options)) {
            $options = static::getOptions();
        }
        $guzzle = new Guzzle();
        $response = $guzzle->request('GET', $url, $options);
        if ($response->getStatusCode() != 200) {
            throw new \Exception("failed to get url content of {$url} with code {$response->getStatusCode()}\n");
        }
        if ($response->getBody()->getSize() == 0) {
            throw new \Exception("{$url} response size is zero\n");
        }
        RequestedUrlSet::set($url);
        if (key_exists('proxy', $options)
            && Config::get('downloader.recycleProxy')
            && !empty($options['proxy'])
        ) {
            ProxyQueue::set($options['proxy']);
        }

        return $response->getBody()->getContents();
    }
}