<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/18
 * Time: 下午8:50
 */
use App\Models\City;
use App\Models\ContractType;
use App\Models\EducationDemand;
use App\Models\ExperienceDemand;
use App\Models\JobFirstType;
use App\Models\JobType;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Database\Capsule\Manager as Capsule;
use Carbon\Carbon;
use App\Models\Job;
use App\Models\Company;
use App\Models\Label;
use GuzzleHttp\Client as Guzzle;

class Spider extends BaseSpider
{
    protected $startUrl = 'http://www.lagou.com/jobs/positionAjax.json?';

    protected $jobUrl = "http://www.lagou.com/jobs/%s.html";

    protected $companyUrl = "http://www.lagou.com/gongsi/%s.html";

    protected $alreadySavedJobSet = 'savedJobSet';

    protected $alreadySavedCompanySet = 'savedCompanySet';

    protected $maxPageCount = 334; //5000/15

    protected $jobQueue = 'jobQueue';

    protected $companyQueue = 'companyQueue';

    protected $removedJobPattern = '/该信息已经被删除鸟/';

    protected $dataTable = [
        'job'                  => 'lagou_job',
        'company'              => 'lagou_company',
        'jobType'              => 'lagou_job_type',
        'jobFirstType'         => 'lagou_job_first_type',
        'companyIndustry'      => 'lagou_company_industry',
        'companyLabel'         => 'lagou_company_label',
        'companyLabelRelation' => 'lagou_company_label_relation',
        'filterDataTable'      => [
            'kd'   => 'lagou_job_kind',
            'city' => 'lagou_city',
            'xl'   => 'lagou_job_education_demand',
            'gj'   => 'lagou_job_experience_demand',
            'hy'   => 'lagou_industry_field',
            'jd'   => 'lagou_company_finance_stage',
            'gx'   => 'lagou_job_contract_type'
        ]

    ];

    protected $filters = [];

    protected $jobDetailPageUrlPattern = '/www.lagou.com\/jobs\/\d+\.html/';

    protected $companyDetailPageUrlPattern = '/www.lagou.com\/gongsi\/\d+\.html/';

    public function loadFilters()
    {
        $dataTables = $this->dataTable['filterDataTable'];
        $dataTables['jobFirstType'] = $this->dataTable['jobFirstType'];
        $dataTables['jobType'] = $this->dataTable['jobType'];
        foreach ($dataTables as $filterTableName) {
            $data = Capsule::table($filterTableName)->select('id', 'name')->get();
            $dataArray = [];
            foreach ($data as $dt) {
                $dataArray[$dt->id] = $dt->name;
            }
            $this->filters[$filterTableName] = $dataArray;
        }
        foreach ($this->filters as $filter) {
            if (empty($filter)) {
                Capsule::table('log')->where('name', '=', 'bootstrap')->delete();
                $this->pushLog("has empty filter, bootstrap again");
                exit(0);
            }
        }
    }

    protected function crawlFilterParameters($url = 'http://www.lagou.com/zhaopin/')
    {
        $content = $this->getUrlContent($url);
        $crawler = new Crawler($content);
        $dataArray[$this->dataTable['filterDataTable']['city']] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/div[1]/div//a')
            ->extract(['_text']);
        $dataArray[$this->dataTable['filterDataTable']['gj']] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/li[1]//a')
            ->extract(['_text']);
        $dataArray[$this->dataTable['filterDataTable']['xl']] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/li[2]//a')
            ->extract(['_text']);
        $dataArray[$this->dataTable['filterDataTable']['jd']] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/li[3]//a')
            ->extract(['_text']);
        $dataArray[$this->dataTable['filterDataTable']['hy']] = $crawler
            ->filterXPath('//div[@id="filterCollapse"]/div[2]/div//a')
            ->extract(['_text']);
        $dataArray[$this->dataTable['filterDataTable']['gx']] = $crawler
            ->filterXPath('//ul[@id="order"]/li[1]/div[3]//li')
            ->extract(['_text']);

        foreach ($dataArray as $table => $data) {
            $this->saveFilterParameters($table, $data);
        }

    }

    protected function crawlJobType($url = 'http://www.lagou.com/')
    {
        Capsule::table($this->dataTable['jobFirstType'])->truncate();
        Capsule::table($this->dataTable['jobType'])->truncate();
        Capsule::table($this->dataTable['filterDataTable']['kd'])->truncate();
        $content = $this->getUrlContent($url);
        $crawler = new Crawler($content);
        $menu = $crawler->filterXPath('//div[@class="menu_box"]');
        $menu->each(function ($m) {
            $firstTypeArray = $m->filterXPath('//h2')->extract(['_text']);
            if (!empty($firstTypeArray)) {
                $firstType = trim($firstTypeArray[0]);
                $newFirstType = JobFirstType::create([
                    'name' => $firstType
                ]);
                $firstTypeId = $newFirstType->id;
                $jobTypes = $m->filterXPath('//dt')->extract(['_text']);
                if (!empty($jobTypes)) {
                    $jobTypesToInsert = [];
                    foreach ($jobTypes as $jobType) {
                        $jobType = trim($jobType);
                        if ($jobType == '高端职位') {
                            $jobType = '高端' . $firstType . '职位';
                        }
                        $jobTypesToInsert[] = [
                            'name'          => $jobType,
                            'first_type_id' => $firstTypeId
                        ];
                    }
                    foreach ($jobTypesToInsert as $key => $jobType) {
                        $newType = JobType::create($jobType);
                        $jobTypeId = $newType->id;
                        $index = $key + 1;
                        $jobKinds = $m->filterXPath("//dd[{$index}]/a")->extract(['_text']);
                        $jobKindsToInsert = [];
                        foreach ($jobKinds as $jobKind) {
                            $jobKindsToInsert[] = [
                                'name'        => $jobKind,
                                'job_type_id' => $jobTypeId,
                                'created_at'  => Carbon::now(),
                                'updated_at'  => Carbon::now()
                            ];
                        }
                        Capsule::table($this->dataTable['filterDataTable']['kd'])->insert($jobKindsToInsert);
                    }
                }
            }
        });
    }

    public function createDatabase()
    {
        if (!Capsule::schema()->hasTable($this->dataTable['job'])) {
            Capsule::schema()->create('lagou_job', function ($table) {
                $table->integer('id')->unsigned()->nullable();
                $table->string('name');
                $table->tinyInteger('type_id')->unsigned()->nullable();
                $table->tinyInteger('salary_min');
                $table->tinyInteger('salary_max');
                $table->tinyInteger('first_type_id')->unsigned()->nullable();
                $table->tinyInteger('experience_demand_id')->unsigned()->nullable();
                $table->tinyInteger('city_id')->unsigned()->nullable();
                $table->tinyInteger('education_demand_id')->unsigned()->nullable();
                $table->integer('company_id')->unsigned()->nullable();
                $table->tinyInteger('contract_type_id')->unsigned()->nullable();
                $table->string('advantage');
                $table->timestamp('create_time')->nullable();
                $table->string('address');
                $table->longText('detail');
                $table->timestamps();
                $table->primary('id');
            });
        }

        if (!Capsule::schema()->hasTable($this->dataTable['company'])) {
            Capsule::schema()->create('lagou_company', function ($table) {
                $table->integer('id')->unsigned()->nullable();
                $table->string('name');
                $table->string('short_name');
                $table->string('logo');
                $table->tinyInteger('city_id')->unsigned()->nullable();
                $table->string('population');
                $table->tinyInteger('job_process_rate_timely')->unsigned()->nullable();
                $table->tinyInteger('days_cost_to_process')->unsigned()->nullable();
                $table->tinyInteger('finance_stage_id')->unsigned()->nullable();
                $table->string('finance_stage_process')->nullable();
                $table->timestamps();
                $table->primary('id');
            });
        }

        foreach ($this->dataTable['filterDataTable'] as $filterTableName) {
            if (!Capsule::schema()->hasTable($filterTableName)) {
                Capsule::schema()->create($filterTableName, function ($table) use ($filterTableName) {
                    $table->increments('id');
                    $table->string('name');
                    if ($filterTableName == $this->dataTable['filterDataTable']['kd']) {
                        $table->tinyInteger('job_type_id')->unsigned()->nullable();
                    }
                    $table->timestamps();
                });
            }
        }

        if (!Capsule::schema()->hasTable($this->dataTable['jobType'])) {
            Capsule::schema()->create($this->dataTable['jobType'], function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->tinyInteger('first_type_id')->unsigned()->nullable();
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable($this->dataTable['jobFirstType'])) {
            Capsule::schema()->create($this->dataTable['jobFirstType'], function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable($this->dataTable['companyIndustry'])) {
            Capsule::schema()->create($this->dataTable['companyIndustry'], function ($table) {
                $table->integer('company_id')->unsigned();
                $table->integer('industry_field_id')->unsigned();
                $table->primary(['company_id', 'industry_field_id']);
            });
        }

        if (!Capsule::schema()->hasTable($this->dataTable['companyLabel'])) {
            Capsule::schema()->create($this->dataTable['companyLabel'], function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Capsule::schema()->hasTable($this->dataTable['companyLabelRelation'])) {
            Capsule::schema()->create($this->dataTable['companyLabelRelation'], function ($table) {
                $table->integer('company_id')->unsigned();
                $table->integer('label_id')->unsigned();
                $table->primary(['company_id', 'label_id']);
            });
        }

        if (!Capsule::schema()->hasTable('log')) {
            Capsule::schema()->create('log', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamp('created_at');
            });
        }
    }

    protected function saveJobToDatabase($job)
    {
        if ($savedJob = Job::where('id', $job['id'])->first()) {
            $savedJob->update($job);
            $this->pushLog("updated job {$job['id']}");

            return $savedJob;
        } else {
            $this->pushLog("created job {$job['id']}");

            return Job::create($job);
        }
    }

    protected function saveCompanyToDatabase($company)
    {
        if (key_exists('industries', $company)) {
            $industries = $company['industries'];
            unset($company['industries']);
        }

        if (key_exists('labels', $company)) {
            $labels = $company['labels'];
            unset($company['labels']);
        }

        if ($savedCompany = Company::where('id', $company['id'])->first()) {
            $savedCompany->update($company);
            $this->pushLog("updated company {$company['id']}");
        } else {
            $this->pushLog("created company {$company['id']}");
            $savedCompany = Company::create($company);
        }

        if (isset($industries)) {
            $industryIds = [];
            foreach ($industries as $industry) {
                $industryIds[] = array_search($industry, $this->filters[$this->dataTable['filterDataTable']['hy']]);
            }
            $savedCompany->industryFields()->sync($industryIds);
        }

        if (isset($labels)) {
            $labelIds = [];
            foreach ($labels as $label) {
                $labelObject = $this->saveLabelToDatabase($label);
                $labelIds[] = $labelObject->id;
            }
            $savedCompany->labels()->sync($labelIds);
        }

        return $savedCompany;
    }

    protected function saveLabelToDatabase($label)
    {
        if ($savedLabel = Label::where('name', $label)->first()) {
            return $savedLabel;
        } else {
            return Label::create(['name' => $label]);
        }
    }

    protected function saveFilterParameters($table, array $data)
    {
        if (count($data) > 1) {
            if ($table == $this->dataTable['filterDataTable']['xl']) {
                $data[0] = '学历' . $data[0];
            } elseif ($table != $this->dataTable['filterDataTable']['gj']) {
                $data = array_slice($data, 1);
            }
            if ($table == $this->dataTable['filterDataTable']['jd']) {
                $data[3] = '上市公司';
            }
            $dataToInsert = [];
            foreach ($data as $value) {
                $dataToInsert[] = ['name'       => $value,
                                   'created_at' => Carbon::now(),
                                   'updated_at' => Carbon::now()
                ];
            }
            Capsule::table($table)->truncate();
            Capsule::table($table)->insert($dataToInsert);
            $count = count($dataToInsert);
            $this->pushLog("saved {$count} items to table {$table}");
        } else {
            throw new \Exception("abnormal filter parameter data, length < 1. table: {$table}\n");
        }
    }

    protected function pushUrlsWithNewFilterToRedis($url)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $queryArray);
        if (count($queryArray) == count($this->dataTable['filterDataTable'])) {
            $this->addPageNumberAndPushToRequestQueue($url, 334);

            return;
        }
        $urls = [];
        foreach ($this->dataTable['filterDataTable'] as $key => $filter) {
            if (!key_exists($key, $queryArray)) {
                $parameters = $this->filters[$filter];
                foreach ($parameters as $parameter) {
                    $urls[] = $url . $key . '=' . urlencode($parameter) . '&';
                }
                break;
            }
        }
        $this->pushToRequestQueue($urls);
    }

    protected function addPageNumberAndPushToRequestQueue($url, $totalPageCount)
    {
        $range = range(1, $totalPageCount);
        $urlsWithPageNumber = [];
        foreach ($range as $pageNumber) {
            $urlsWithPageNumber[] = $url . 'pn=' . $pageNumber;
        }
        $this->pushLog("push {$totalPageCount} urls with page number into redis");
        $this->pushToRequestQueue($urlsWithPageNumber);
    }

    protected function parseJobListUrlWithoutPageNumber($url)
    {
        $content = $this->getUrlContent($url);
        $jsonContent = json_decode($content);
        $totalPageCount = $jsonContent->content->totalPageCount;
        $totalJobCount = $jsonContent->content->totalCount;
        if ($totalJobCount == 5000) {
            $this->pushLog("encounter max count 5000");
            $this->pushUrlsWithNewFilterToRedis($url);
        } elseif ($totalPageCount > 0) {
            $this->addPageNumberAndPushToRequestQueue($url, $totalPageCount);
        } else {
            $this->pushLog("miss match {$url} {$content} {$totalPageCount} {$totalJobCount}");
        }
    }

    protected function parseJobListAjaxUrl($url)
    {
        $jsonContent = json_decode($this->getUrlContent($url));
        $results = $jsonContent->content->result;
        if (!empty($results)) {
            $formattedResults = $this->parseJobJsonArray($results);
            $jobsToSave = $formattedResults['jobs'];
            $companiesToSave = $formattedResults['companies'];
            foreach ($jobsToSave as $job) {
                $this->getRedisClient()->lpush($this->jobQueue, json_encode($job));
            }
            foreach ($companiesToSave as $company) {
                $this->getRedisClient()->lpush($this->companyQueue, json_encode($company));
            }
        }
    }

    protected function parseJobJsonArray($results)
    {
        Capsule::connection()->reconnect();
        $jobs = [];
        $companies = [];
        foreach ($results as $result) {
            if (!$this->getRedisClient()->sismember($this->alreadySavedJobSet, $result->positionId)) {
                $firstTypeId = array_search($result->positionFirstType, $this->filters[$this->dataTable['jobFirstType']]);
                if (!$firstTypeId) {
                    $firstTypeId = null;
                    $this->log("job->" . $result->positionId . ": not found first type->" . $result->positionFirstType);
                }
                $typeId = array_search($result->positionType, $this->filters[$this->dataTable['jobType']]);
                if (!$typeId) {
                    if ($firstTypeId && !empty($result->positionType)) {
                        $newType = JobType::create([
                            'name'          => $result->positionType,
                            'first_type_id' => $firstTypeId
                        ]);
                        $typeId = $newType->id;
                    } else {
                        $typeId = null;
                        $this->log("job->" . $result->positionId . ": not found first type->" . $result->positionType);
                    }
                }
                $expDemandId = array_search($result->workYear, $this->filters[$this->dataTable['filterDataTable']['gj']]);
                if (!$expDemandId) {
                    if (!empty($result->workYear)) {
                        $newExpDemand = ExperienceDemand::create(['name' => $result->workYear]);
                        $expDemandId = $newExpDemand->id;
                    } else {
                        $expDemandId = null;
                    }
                }
                $cityId = array_search($result->city, $this->filters[$this->dataTable['filterDataTable']['city']]);
                if (!$cityId) {
                    if (!empty($result->city)) {
                        $newCity = City::create(["name" => $result->city]);
                        $cityId = $newCity->id;
                    } else {
                        $cityId = null;
                    }
                }
                $education = array_search($result->education, $this->filters[$this->dataTable['filterDataTable']['xl']]);
                if (!$education) {
                    if (!empty($result->education)) {
                        $newEducation = EducationDemand::create(['name' => $result->education]);
                        $education = $newEducation->id;
                    } else {
                        $education = null;
                    }
                }
                $contract = array_search($result->jobNature, $this->filters[$this->dataTable['filterDataTable']['gx']]);
                if (!$contract) {
                    if (!empty($result->jobNature)) {
                        $newContract = ContractType::create(['name' => $result->jobNature]);
                        $contract = $newContract->id;
                    } else {
                        $contract = null;
                    }
                }
                $jobs[] = [
                    'id'                   => $result->positionId,
                    'name'                 => $result->positionName,
                    'type_id'              => $typeId,
                    'salary_min'           => $this->getSalary($result->salary, 'min'),
                    'salary_max'           => $this->getSalary($result->salary, 'max'),
                    'first_type_id'        => $firstTypeId,
                    'experience_demand_id' => $expDemandId,
                    'city_id'              => $cityId,
                    'education_demand_id'  => $education,
                    'company_id'           => $result->companyId,
                    'contract_type_id'     => $contract,
                    'advantage'            => $result->positionAdvantage,
                    'create_time'          => $result->createTime,
                ];
                $financeStageArray = explode('(', $result->financeStage);
                $financeStage = $financeStageArray[0];
                if (count($financeStageArray) == 1) {
                    $financeStageProcess = null;
                } else {
                    $financeStageProcess = rtrim($financeStageArray[1], ')');
                }
                $this->getRedisClient()->sadd($this->alreadySavedJobSet, $result->positionId);
                $this->pushToRequestQueue(sprintf($this->jobUrl, $result->positionId));
                if (!$this->getRedisClient()->sismember($this->alreadySavedCompanySet, $result->companyId)) {
                    $finance = array_search($financeStage, $this->filters[$this->dataTable['filterDataTable']['jd']]);
                    if (!$finance) {
                        $finance = null;
                        $this->log("company->" . $result->companyId . ": not found finance stage->" . $financeStage);
                    }
                    $companies[] = [
                        'id'                    => $result->companyId,
                        'name'                  => $result->companyName,
                        'short_name'            => $result->companyShortName,
                        'logo'                  => 'http://www.lagou.com/' . $result->companyLogo,
                        'city_id'               => $cityId,
                        'population'            => $result->companySize,
                        'finance_stage_id'      => $finance,
                        'finance_stage_process' => $financeStageProcess,
                    ];
                    $this->getRedisClient()->sadd($this->alreadySavedCompanySet, $result->companyId);
                    $this->pushToRequestQueue(sprintf($this->companyUrl, $result->companyId));
                }
            }
        }

        return ['jobs' => $jobs, 'companies' => $companies];
    }

    protected function getSalary($range, $type)
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

    protected function parseJobDetailPage($url)
    {
        $attributes = [];
        $content = $this->getUrlContent($url);
        if (preg_match($this->removedJobPattern, $content)) {
            throw new \Exception("{$url} this job is removed\n", 444);
        }
        $crawler = new Crawler($content);
        $attributes['id'] = intval(filter_var($url, FILTER_SANITIZE_NUMBER_INT));
        $attributes['address'] = trim($crawler->filterXPath('//dl[@class="job_company"]/dd[1]/div[1]')->text());
        $rawDetail = $crawler->filterXPath('//dd[@class="job_bt"]')->html();
        $attributes['detail'] = trim(str_replace('<h3 class="description">职位描述</h3>', '', $rawDetail));
        $this->getRedisClient()->lpush($this->jobQueue, json_encode($attributes));
    }

    protected function parseCompanyDetailPage($url)
    {
        $attributes = [];
        $crawler = new Crawler($this->getUrlContent($url));
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

        $companyIndustryString = $crawler->filterXPath('//i[@class="type"]')->siblings()->text();
        $attributes['industries'] = [];
        foreach (explode(',', $companyIndustryString) as $industry) {
            $attributes['industries'][] = trim($industry);
        }

        $companyLabelArray = $crawler->filterXPath('//div[@class="tags_warp"]//li')->extract('_text');
        $attributes['labels'] = [];
        foreach ($companyLabelArray as $label) {
            $attributes['labels'][] = trim($label);
        }
        $this->getRedisClient()->lpush($this->companyQueue, json_encode($attributes));
    }

    protected function parseUrlFromRequestQueue()
    {
        $requestUrl = $this->getRedisClient()->rpop($this->requestQueue);

        try {
            if (!empty($requestUrl)) {
                if (preg_match($this->jobDetailPageUrlPattern, $requestUrl)) {
                    $this->pushLog("start to parse job detail page {$requestUrl}");
                    $this->parseJobDetailPage($requestUrl);
                } elseif (preg_match($this->companyDetailPageUrlPattern, $requestUrl)) {
                    $this->pushLog("start to parse company detail page {$requestUrl}");
                    $this->parseCompanyDetailPage($requestUrl);
                } else {
                    parse_str($requestUrl, $queryArray);
                    if (array_key_exists('pn', $queryArray)) {
                        $this->pushLog("start to parse job list page {$requestUrl}");
                        $this->parseJobListAjaxUrl($requestUrl);
                    } else {
                        $this->pushLog("start to parse job list page without page number {$requestUrl}");
                        $this->parseJobListUrlWithoutPageNumber($requestUrl);
                    }
                }
            } else {
                $this->pushLog("empty request url");
            }
        } catch (\Exception $e) {
            if ($e->getCode() == 444) {
                $this->pushLog($e->getMessage());
                exit(0);
            }
            $this->pushLog("catch exception when parsing url {$requestUrl}");
            $this->pushLog($e->getMessage());
            $this->pushLog($e->getFile());
            $this->pushLog($e->getLine());
            $this->getRedisClient()->lpush($this->requestQueue, $requestUrl);
            exit(0);
        }

    }

    public function loadProxies()
    {
        $proxyApi = Config::get('setting.proxyApi');
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
                $this->getRedisClient()->lpush($this->proxyQueue, $proxies);
            }
        } else {
            die("please set proxy api if you want to use proxy\n");
        }
    }

    public function dataPersistence()
    {
        Capsule::connection()->reconnect();
        while (true) {
            if ($this->getRedisClient()->get('status') == $this->status['stop']) {
                die("stop");
            }
            if ($this->getRedisClient()->llen($this->jobQueue) == 0) {
                $this->pushLog("all jobs are saved");
                break;
            }
            try {
                $this->pushLog("saving job to database");
                $job = json_decode($this->getRedisClient()->rpop($this->jobQueue), true);
                $this->saveJobToDatabase($job);
            } catch (\Exception $e) {
                $this->pushLog("catch error when saving job to database");
                $this->pushLog($e->getMessage());
                die();
            }
        }
        while (true) {
            if ($this->getRedisClient()->get('status') == $this->status['stop']) {
                die("stop");
            }
            if ($this->getRedisClient()->llen($this->companyQueue) == 0) {
                $this->pushLog("all companies are saved");
                break;
            }
            try {
                $this->pushLog("saving company to database");
                $company = json_decode($this->getRedisClient()->rpop($this->companyQueue), true);
                $this->saveCompanyToDatabase($company);
            } catch (\Exception $e) {
                $this->pushLog("catch error when saving company to database");
                $this->pushLog($e->getMessage());
                die();
            }

        }
    }

    public function run()
    {
        while (true) {
            if ($this->getRedisClient()->exists('status')) {
                if ($this->getRedisClient()->get('status') == $this->status['stop']) {
                    die("stop");
                }
            } else {
                $this->getRedisClient()->set('status', $this->status['run']);
            }

            $requestQueueLength = $this->getRedisClient()->llen($this->requestQueue);
            if ($requestQueueLength == 0) {
                $this->pushLog("request queue is empty, finish");
                break;
            }
            $connectionCount = min($requestQueueLength, $this->maxConnectionCount);

            for ($i = 0; $i < $connectionCount; $i++) {
                $pid = pcntl_fork();
                if ($pid == -1) {
                    $this->pushLog("fail to fork");
                    exit(0);
                }

                if (!$pid) {
                    $this->parseUrlFromRequestQueue();
                    exit(0);
                }
            }
            while (pcntl_waitpid(0, $status) != -1) {
                $message = 'abnormally!!!!';
                $status = pcntl_wexitstatus($status);
                if (pcntl_wifexited($status)) {
                    $message = 'successfully';
                }
                $this->pushLog(date("H:i:s") . "--------process exit {$message}--------");
            }
        }
    }

    public function log($name)
    {
        Capsule::table('log')->insert([
            'name'       => $name,
            'created_at' => Carbon::now()
        ]);
    }

    public function bootstrap()
    {
        if (Config::get('setting.useProxy')) {
            if ($this->getRedisClient()->llen($this->proxyQueue) < Config::get('setting.proxyMinimumCount')) {
                $this->loadProxies();
            }
        }
        while (true) {
            try {
                if (Capsule::table('log')->where('name', '=', 'bootstrap')->count() == 0) {
                    $this->crawlJobType();
                    $this->crawlFilterParameters();
                    $this->initRequestQueue();
                    $this->log('bootstrap');
                }
                break;
            } catch (\Exception $e) {
                $this->pushLog("catch exception when bootstrapping");
                $this->pushLog($e->getMessage());
                $this->pushLog($e->getFile());
                $this->pushLog($e->getLine());
                $this->pushLog("bootstrap again");
            }
        }
        $this->loadFilters();
    }

    public function startToCrawl()
    {
        $this->log('start');
        $this->bootstrap();
        $this->run();
        $this->dataPersistence();
        $this->log('finish');
    }
}