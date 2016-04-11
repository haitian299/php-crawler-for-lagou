<?php namespace App\Parsers;

use App\RedisModels\Filter;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 17:50
 */
class JobFilterParser implements BaseParser
{
    public static function parse($url, $content)
    {
        $crawler = new Crawler($content);
        $filterArray['city'] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/div[1]/div//a')
            ->extract(['_text']);
        $filterArray['gj'] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/li[1]//a')
            ->extract(['_text']);
        $filterArray['xl'] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/li[2]//a')
            ->extract(['_text']);
        $filterArray['jd'] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/li[3]//a')
            ->extract(['_text']);
        $filterArray['hy'] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/div[2]/div//a')
            ->extract(['_text']);
        $filterArray['gx'] = $crawler
            ->filterXPath('//ul[@id="order"]/li[1]/div[3]//li')
            ->extract(['_text']);

        foreach ($filterArray as $key => $value) {
            Filter::fset($key, $value);
        }
    }
}