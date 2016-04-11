<?php namespace App\Parsers;

use App\Config;
use App\DatabaseModels\Job;
use App\RedisModels\LogQueue;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 14:54
 */
class JobDetailPageParser implements BaseParser
{
    protected static $removedJobPattern = '/该信息已经被删除鸟/';

    public static function parse($url, $content)
    {
        $attributes = [];
        if (preg_match(static::$removedJobPattern, $content)) {
            throw new \Exception("{$url} this job is removed\n", Config::get('spider.discardUrlExceptionCode'));
        }
        $crawler = new Crawler($content);
        $attributes['id'] = intval(filter_var($url, FILTER_SANITIZE_NUMBER_INT));
        $attributes['address'] = trim($crawler
            ->filterXPath('//dl[@class="job_company"]/dd[1]/div[1]')->text());
        $rawDetail = $crawler->filterXPath('//dd[@class="job_bt"]')->html();
        $attributes['detail'] = trim(str_replace('<h3 class="description">职位描述</h3>',
            '', $rawDetail));
        Job::updateOrCreate([
            'id' => $attributes['id']
        ], $attributes);
        LogQueue::set("saved job " . $attributes['id']);
    }
}