<?php namespace App\Parsers;

use App\RedisModels\Filter;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 15:44
 */
class JobKindParser implements BaseParser
{
    public static function parse($url, $content)
    {
        $crawler = new Crawler($content);
        $jobKinds = $crawler->filterXPath('//div[@class="menu_box"]//dd/a')->extract(['_text']);
        Filter::fset('kd', $jobKinds);
    }
}