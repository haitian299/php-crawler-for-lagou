<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/18
 * Time: 下午8:50
 */
use App\RedisModels\LogQueue;
use App\RedisModels\RequestQueue;
use App\RedisModels\Status;

class Spider
{
    protected $rules;

    protected $maxConnectionCount = 1;

    protected $startUrls;

    public function __construct()
    {
        if (Config::get('spider.dataPersistenceToDatabase') == true) {
            Database::boot();
        }
        if (Config::get('spider.maxConnectionCount')) {
            $this->maxConnectionCount = Config::get('spider.maxConnectionCount');
        }
        $this->rules = Config::get('spider.rules');
        $this->startUrls = Config::get('spider.startUrls');
    }

    protected function initRequestQueue(array $urls = array())
    {
        if (RequestQueue::length() == 0) {
            if (empty($urls)) {
                $urls = $this->startUrls;
            }
            RequestQueue::set($urls);
        }
    }

    protected function run()
    {
        while (true) {

            if (Status::exist()) {
                if (Status::isStopped()) {
                    die("stop");
                }
            } else {
                Status::run();
            }

            $requestQueueLength = RequestQueue::length();
            if ($requestQueueLength == 0) {
                LogQueue::set("request queue is empty, finish");
                break;
            }
            $connectionCount = min($requestQueueLength, $this->maxConnectionCount);

            for ($i = 0; $i < $connectionCount; $i++) {
                $pid = pcntl_fork();
                if ($pid == -1) {
                    LogQueue::set("fail to fork");
                    exit(0);
                }

                if (!$pid) {
                    $requestUrl = RequestQueue::get();
                    try {
                        if (!empty($requestUrl)) {
                            LogQueue::set("Downloader -> {$requestUrl}");
                            $content = Downloader::getUrlContent($requestUrl);
                            foreach ($this->rules as $rule) {
                                if (preg_match($rule['pattern'], $requestUrl)) {
                                    LogQueue::set($rule['parser'] . " -> {$requestUrl}");
                                    call_user_func($rule['parser'], $requestUrl, $content);
                                    exit(0);
                                }
                            }
                            LogQueue::set("no parser for {$requestUrl}");
                        } else {
                            LogQueue::set("empty request url");
                        }
                    } catch (\Exception $e) {
                        if ($e->getCode() != Config::get('spider.discardUrlExceptionCode')) {
                            RequestQueue::set($requestUrl);
                        }
                        LogQueue::set("catch exception when parsing url {$requestUrl}");
                        LogQueue::set($e->getMessage());
                        LogQueue::set($e->getCode());
                        LogQueue::set($e->getFile());
                    }
                    exit(0);
                }
            }
            while (pcntl_waitpid(0, $status) != -1) {
                $message = 'abnormally!!!!';
                $status = pcntl_wexitstatus($status);
                if (pcntl_wifexited($status)) {
                    $message = 'successfully';
                }
                LogQueue::set(date("H:i:s") . "--------process exit {$message}--------");
            }
        }
    }

    public function startToCrawl()
    {
        LogQueue::set('start to crawl');
        $this->initRequestQueue();
        $this->run();
        LogQueue::set('finish crawling');
    }
}