<?php
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/1
 * Time: 上午11:40
 */
return [
    'maxConnectionCount'        => 10,
    'dataPersistenceToDatabase' => true,
    'rules'                     => [
        ['pattern' => '/www\.lagou\.com\/jobs\/positionAjax\.json/',
         'parser'  => '\App\Parsers\JobListParser::parse'
        ],
        ['pattern' => '/www\.lagou\.com\/jobs\/\d+\.html/',
         'parser'  => '\App\Parsers\JobDetailPageParser::parse'
        ],
        ['pattern' => '/www\.lagou\.com\/gongsi\/\d+\.html/',
         'parser'  => '\App\Parsers\CompanyDetailPageParser::parse'
        ],
        ['pattern' => '/www\.lagou\.com$/',
         'parser'  => '\App\Parsers\JobKindParser::parse'
        ],
        ['pattern' => '/www\.lagou\.com\/zhaopin\/$/',
         'parser'  => '\App\Parsers\JobFilterParser::parse'
        ],
    ],
    'startUrls'                 => [
        'http://www.lagou.com',
        'http://www.lagou.com/zhaopin/',
        'http://www.lagou.com/jobs/positionAjax.json?'
    ],
    'discardUrlExceptionCode'   => 444,
];