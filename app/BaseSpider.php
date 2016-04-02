<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/30
 * Time: 下午2:24
 */
use GuzzleHttp\Client as Guzzle;
use App\Database;
use Illuminate\Database\Capsule\Manager as Capsule;
use Predis\Client as Redis;

abstract class BaseSpider
{
    protected $redis;

    protected $startUrl;

    protected $requestQueue = 'requestQueue';

    protected $alreadyRequestedUrlSet = 'requestedUrlSet';

    protected $maxConnectionCount;

    protected $options = [
        'headers' => [
            'Accept-Language' => 'zh-CN,zh;q=0.8,en;q=0.6',
            'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36',
            'Accept-Encoding' => 'gzip, deflate, sdch',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Host'            => 'www.lagou.com',
            'Referer'         => 'http://www.lagou.com/zhaopin/',
//            'Cookie'          => 'tencentSig=5138582528; LGUID=20160316004014-97ccc94e-eacc-11e5-b07c-5254005c3644; user_trace_token=20160316004014-fc64a971bbdd4de5bfb7770def055162; tencentSig=6656587776; index_location_city=%E5%85%A8%E5%9B%BD; LGMOID=20160330113123-EF33C3FE5258CD34E313540B7F87ED24; HISTORY_POSITION=1653176%2C4k-6k%2C%E5%85%AD%E7%A7%A6%E4%BA%92%E5%8A%A8%2CUI%E8%AE%BE%E8%AE%A1%E5%B8%88%7C893951%2C18k-25k%2C%E5%8C%97%E5%A4%A7%E5%8C%BB%E4%BF%A1%2CJava%EF%BC%88XAP%E9%A1%B9%E7%9B%AE%E7%BB%84%EF%BC%89%7C1653417%2C16k-32k%2C%E7%99%BE%E5%BA%A6%2CJava%7C1455021%2C20k-35k%2C%E5%92%8C%E5%88%9B%E7%A7%91%E6%8A%80%EF%BC%88%E7%BA%A2%E5%9C%88%E8%90%A5%E9%94%80%EF%BC%89%2CJava%7C1652980%2C10k-18k%2C%E7%9E%AC%E8%81%94%E8%BD%AF%E4%BB%B6%E7%A7%91%E6%8A%80%EF%BC%88%E5%8C%97%E4%BA%AC%EF%BC%89%E6%9C%89%E9%99%90%E5%85%AC%E5%8F%B8%2CJava%7C; JSESSIONID=8910266B4C5C498C4AED48B462C45F71; PRE_UTM=; PRE_HOST=; PRE_SITE=; PRE_LAND=https%3A%2F%2Fpassport.lagou.com%2Flogin%2Flogin.html%3Fts%3D1459511285429%26serviceId%3Dlagou%26service%3Dhttp%25253A%25252F%25252Fwww.lagou.com%25252Fjobs%25252FpositionAjax.json%252525253Fkd%25253D%252525E6%25252595%252525B0%252525E6%2525258D%252525AE%252525E4%252525BB%25252593%252525E5%252525BA%25252593%252526%26action%3Dlogin%26signature%3D2ED608B0CE1F7F389E006B8FEB2340DE; SEARCH_ID=bb23b4d953cf4df6b13774bcdadc428f; _gat=1; Hm_lvt_4233e74dff0ae5bd0a3d81c6ccf756e6=1458196220,1458304635,1458662009,1458748964; Hm_lpvt_4233e74dff0ae5bd0a3d81c6ccf756e6=1459511983; _ga=GA1.2.660112600.1458060014; LGSID=20160401194805-98e95b82-f7ff-11e5-bac1-5254005c3644; LGRID=20160401195943-38d7ed79-f801-11e5-bac1-5254005c3644'
        ]
    ];

    public function __construct()
    {
        $this->initDatabaseConnection();
        if (empty($this->maxConnectionCount)) {
            $this->maxConnectionCount = Config::get('setting.maxConnectionCount');
        }
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

    protected function getUrlContent($url, $options = null)
    {
        if (empty($options)) {
            $options = $this->options;
        }
        $guzzle = new Guzzle();
        $response = $guzzle->request('GET', $url, $options);
        if ($response->getStatusCode() != 200) {
            echo("failed to get url content of {$url} with code {$response->getStatusCode()}\n");
        }
        $this->getRedisClient()->sadd($this->alreadyRequestedUrlSet, $url);

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
                echo("{$it} is already requested, so pass\n");
            } else {
                echo("pushed {$it} into request queue\n");
                $this->getRedisClient()->lpush($this->requestQueue, $it);
            }
        }
    }
}