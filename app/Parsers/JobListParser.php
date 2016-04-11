<?php namespace App\Parsers;

use App\Config;
use App\DatabaseModels\Company;
use App\DatabaseModels\Job;
use App\RedisModels\Filter;
use App\RedisModels\LogQueue;
use App\RedisModels\ParsedCompanySet;
use App\RedisModels\ParsedJobSet;
use App\RedisModels\RequestQueue;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 00:31
 */
class JobListParser implements BaseParser
{

    protected static $jobUrl       = "http://www.lagou.com/jobs/%s.html";
    protected static $domain       = 'http://www.lagou.com/';
    protected static $companyUrl   = "http://www.lagou.com/gongsi/%s.html";
    protected static $maxPageCount = 334;

    public static function parse($url, $content)
    {
        parse_str($url, $queryArray);
        if (array_key_exists('pn', $queryArray)) {
            static::parseJobList($content);
        } else {
            static::decorateUrl($url, $content);
        }
    }

    protected static function parseJobList($content)
    {
        $jsonContent = json_decode($content);
        $results = $jsonContent->content->result;
        if (!empty($results)) {
            $formattedResults = static::parseJobJsonArray($results);
            $jobsToSave = $formattedResults['jobs'];
            $companiesToSave = $formattedResults['companies'];
            foreach ($jobsToSave as $job) {
                Job::updateOrCreate([
                    'id' => $job['id']
                ], $job);
            }
            foreach ($companiesToSave as $company) {
                Company::updateOrCreate([
                    'id' => $company['id']
                ], $company);
            }
        }
    }

    protected static function decorateUrl($url, $content)
    {
        $jsonContent = json_decode($content);
        $totalPageCount = $jsonContent->content->totalPageCount;
        $totalJobCount = $jsonContent->content->totalCount;
        if ($totalJobCount == 5000) {
            LogQueue::set("encounter max count 5000");
            static::decorateUrlWithFilters($url);
        } elseif ($totalPageCount > 0) {
            static::decorateUrlWithPageNumber($url, $totalPageCount);
        } else {
            throw new \Exception("error in decorating url: total job count {$totalJobCount}, total page count {$totalPageCount}");
        }
    }

    protected static function decorateUrlWithPageNumber($url, $totalPageCount)
    {
        $range = range(1, $totalPageCount);
        $urlsWithPageNumber = [];
        foreach ($range as $pageNumber) {
            $urlsWithPageNumber[] = $url . 'pn=' . $pageNumber;
        }
        LogQueue::set("push {$totalPageCount} urls with page number into redis");
        RequestQueue::set($urlsWithPageNumber);
    }

    protected static function decorateUrlWithFilters($url)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $queryArray);
        if (count($queryArray) == count(Config::get('lagou.filters'))) {
            static::decorateUrlWithPageNumber($url, static::$maxPageCount);

            return;
        }
        $urls = [];
        foreach (Config::get('lagou.filters') as $filter) {
            if (!key_exists($filter, $queryArray)) {
                $parameters = Filter::fget($filter);
                if (empty($parameters)) {
                    throw new \Exception("filter {$filter} is empty!!!");
                }
                foreach ($parameters as $parameter) {
                    $urls[] = $url . $filter . '=' . urlencode($parameter) . '&';
                }
                break;
            }
        }
        RequestQueue::set($urls);
    }

    protected static function parseJobJsonArray($results)
    {
        $jobs = [];
        $companies = [];
        foreach ($results as $result) {
            if (!ParsedJobSet::has($result->positionId)) {
                //collect job information
                $jobs[] = [
                    'id'                => $result->positionId,
                    'name'              => $result->positionName,
                    'type'              => $result->positionType,
                    'salary_min'        => static::getSalary($result->salary, 'min'),
                    'salary_max'        => static::getSalary($result->salary, 'max'),
                    'first_type'        => $result->positionFirstType,
                    'experience_demand' => $result->workYear,
                    'city'              => $result->city,
                    'education_demand'  => $result->education,
                    'company_id'           => $result->companyId,
                    'contract_type'     => $result->jobNature,
                    'advantage'         => $result->positionAdvantage,
                    'create_time'       => $result->createTime,
                ];
                ParsedJobSet::set($result->positionId);
                RequestQueue::set(sprintf(static::$jobUrl, $result->positionId));

                //collect company information
                $financeStageArray = explode('(', $result->financeStage);
                $financeStage = $financeStageArray[0];
                if (count($financeStageArray) == 1) {
                    $financeStageProcess = null;
                } else {
                    $financeStageProcess = rtrim($financeStageArray[1], ')');
                }
                if (!ParsedCompanySet::has($result->companyId)) {
                    $companies[] = [
                        'id'                    => $result->companyId,
                        'name'                  => $result->companyName,
                        'short_name'            => $result->companyShortName,
                        'logo'                  => static::$domain . $result->companyLogo,
                        'city'                  => $result->city,
                        'population'            => $result->companySize,
                        'finance_stage'         => $financeStage,
                        'finance_stage_process' => $financeStageProcess,
                    ];
                    ParsedCompanySet::set($result->companyId);
                    RequestQueue::set(sprintf(static::$companyUrl, $result->companyId));
                }
            }
        }

        return ['jobs' => $jobs, 'companies' => $companies];
    }

    protected static function getSalary($range, $type)
    {
        $salaryRange = explode('-', $range);
        $count = count($salaryRange);
        if ($count == 1) {
            return intval($salaryRange[0]);
        }
        if ($count == 2) {
            if ($type == 'min') {
                return intval($salaryRange[0]);
            } else {
                return intval($salaryRange[1]);
            }
        }
    }
}