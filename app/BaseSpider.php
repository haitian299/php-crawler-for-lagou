<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/30
 * Time: 下午2:24
 */
use GuzzleHttp\Client as Guzzle;
use Illuminate\Database\Capsule\Manager as Capsule;
use Predis\Client as Redis;

abstract class BaseSpider
{
    protected $redis;

    protected $startUrl;

    protected $status = [
        'run'  => '1',
        'stop' => '0'
    ];

    protected $requestQueue = 'requestQueue';

    protected $alreadyRequestedUrlSet = 'requestedUrlSet';

    protected $maxConnectionCount;

    protected $proxyQueue = 'proxyQueue';

    protected $logQueue = 'logQueue';

    protected $options = [
        'headers' => [
            'Accept-Language' => 'zh-CN,zh;q=0.8,en;q=0.6',
            'Accept-Encoding' => 'gzip, deflate, sdch',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Host'            => 'www.lagou.com',
            'Referer'         => 'http://www.lagou.com/zhaopin/',
        ]
    ];

    protected $userAgents = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:2.0b8) Gecko/20100101 Firefox/4.0b8',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_5_8) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.68 Safari/534.24',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_4) AppleWebKit/534.30 (KHTML, like Gecko) Chrome/12.0.742.100 Safari/534.30',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_6) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.12 Safari/534.24',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_6) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.698.0 Safari/534.24',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_7) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.68 Safari/534.24',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_0) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.0 Safari/534.24',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X; U; en; rv:1.8.0) Gecko/20060728 Firefox/1.5.0 Opera 9.27',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-GB; rv:1.9.0.6) Gecko/2009011912 Firefox/3.0.6',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.0.10) Gecko/2009122115 Firefox/3.0.17',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.0.6) Gecko/2009011912 Firefox/3.0.6',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.1b3pre) Gecko/20090204 Firefox/3.1b3pre',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.1b4) Gecko/20090423 Firefox/3.5b4 GTB5',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; fr; rv:1.9.1b4) Gecko/20090423 Firefox/3.5b4',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; it; rv:1.9b4) Gecko/2008030317 Firefox/3.0b4',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; ko; rv:1.9.1b2) Gecko/20081201 Firefox/3.1b2',
        'Mozilla/5.0 (Windows; U; Windows NT 5.1; en) AppleWebKit/526.9 (KHTML, like Gecko) Version/4.0dp1 Safari/526.8',
        'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9',
        'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/532+ (KHTML, like Gecko) Version/4.0.2 Safari/530.19.1',
        'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_5_7; en-us) AppleWebKit/531.2+ (KHTML, like Gecko) Version/4.0.1 Safari/530.18',
        'Mozilla/5.0 (Windows; U; Windows NT 6.0; ru-RU) AppleWebKit/528.16 (KHTML, like Gecko) Version/4.0 Safari/528.16',
        'Mozilla/5.0 (Windows; U; Windows NT 5.1; cs-CZ) AppleWebKit/525.28.3 (KHTML, like Gecko) Version/3.2.3 Safari/525.29',
        'Mozilla/5.0 (X11; U; Linux i686 (x86_64); en-US) AppleWebKit/532.0 (KHTML, like Gecko) Chrome/4.0.202.2 Safari/532.0',
        'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13',
        'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.30 Safari/525.13'
    ];

    public function __construct()
    {
        $this->initDatabaseConnection();
        if (empty($this->maxConnectionCount)) {
            $this->maxConnectionCount = Config::get('setting.maxConnectionCount');
        }
        $this->options['connect_timeout'] = Config::get('setting.connectTimeOut');
        $this->options['timeout'] = Config::get('setting.responseTimeOut');
    }

    public function getRedisClient()
    {
        $pid = getmypid();
        if (empty($this->redis[$pid])) {
            $this->redis[$pid] = new Redis(Config::get('database.redis.default'));
        }

        return $this->redis[$pid];
    }

    protected function initDatabaseConnection()
    {
        $capsule = new Capsule();
        $database = Config::get('database.default');
        $capsule->addConnection(Config::get("database.connections.{$database}"));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    public function createDatabase()
    {
        //
    }

    protected function getOptions()
    {
        $options = $this->options;
        $userAgent = $this->userAgents[array_rand($this->userAgents)];
        $options['headers']['User-Agent'] = $userAgent;
        if (Config::get('setting.useProxy')) {
            $options['proxy'] = $this->getProxy();
        }

        return $options;
    }

    protected function getProxy()
    {
        if ($this->getRedisClient()->llen($this->proxyQueue) < Config::get('setting.proxyMinimumCount')) {
            $this->loadProxies();
        }
        $proxy = $this->getRedisClient()->rpop($this->proxyQueue);
        if (empty($proxy)) {
            throw new \Exception("run out of proxy\n");
        }

        return $proxy;
    }

    protected function getUrlContent($url, $options = null)
    {
        if (empty($options)) {
            $options = $this->getOptions();
        }
        $guzzle = new Guzzle();
        $response = $guzzle->request('GET', $url, $options);
        if ($response->getStatusCode() != 200) {
            throw new \Exception("failed to get url content of {$url} with code {$response->getStatusCode()}\n");
        }
        if ($response->getBody()->getSize() == 0) {
            throw new \Exception("{$url} response size is zero\n");
        }
        $this->getRedisClient()->sadd($this->alreadyRequestedUrlSet, $url);
        if (key_exists('proxy', $options)) {
            if (!empty($options['proxy'])) {
                $this->getRedisClient()->lpush($this->proxyQueue, $options['proxy']);
            }
        }

        return $response->getBody()->getContents();
    }

    public function initRequestQueue($url = null)
    {
        if ($this->getRedisClient()->llen($this->requestQueue) == 0) {
            if (empty($url)) {
                $url = $this->startUrl;
            }
            $this->pushToRequestQueue($url);
        }
    }

    public function pushToRequestQueue($item)
    {
        if (is_string($item)) {
            $item = [$item];
        }
        foreach ($item as $it) {
            if ($this->getRedisClient()->sismember($this->alreadyRequestedUrlSet, $it)) {
                $this->pushLog("{$it} is already requested, so pass");
            } else {
                $this->pushLog("pushed {$it} into request queue");
                $this->getRedisClient()->lpush($this->requestQueue, $it);
            }
        }
    }

    public function loadProxies()
    {
        //
    }

    public function pushLog($message)
    {
        if (!empty($message)) {
            $this->getRedisClient()->lpush($this->logQueue, $message);
        }
        $this->getRedisClient()->ltrim($this->logQueue, 0, Config::get('setting.maxLogCount'));
    }
}