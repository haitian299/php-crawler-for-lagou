<?php namespace App\Parsers;

use App\DatabaseModels\Company;
use App\RedisModels\LogQueue;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 15:26
 */
class CompanyDetailPageParser implements BaseParser
{
    public static function parse($url, $content)
    {
        $attributes = [];
        $crawler = new Crawler($content);
        $attributes['id'] = intval(filter_var($url, FILTER_SANITIZE_NUMBER_INT));

        $jobProcessRateTimely = trim($crawler
            ->filterXPath('//div[@class="company_data"]/ul/li[2]/strong')->text());
        if ($jobProcessRateTimely == '暂无') {
            $attributes['job_process_rate_timely'] = null;
        } else {
            $attributes['job_process_rate_timely'] = intval($jobProcessRateTimely);
        }

        $daysCostToProcess = trim($crawler
            ->filterXPath('//div[@class="company_data"]/ul/li[3]/strong')->text());
        if ($daysCostToProcess == '暂无') {
            $attributes['days_cost_to_process'] = null;
        } else {
            $attributes['days_cost_to_process'] = intval($daysCostToProcess);
        }

        $attributes['industries'] = trim($crawler->filterXPath('//i[@class="type"]')->siblings()->text());

        $labelArray = $crawler->filterXPath('//div[@class="tags_warp"]//li')->extract('_text');

        array_walk($labelArray, function (&$value) {
            $value = trim($value);
        });

        $attributes['labels'] = implode(',', $labelArray);

        Company::updateOrCreate([
            'id' => $attributes['id']
        ], $attributes);
        LogQueue::set("saved company " . $attributes['id']);
    }
}