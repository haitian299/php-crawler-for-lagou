<?php
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/1
 * Time: 上午11:40
 */
return [
    'maxConnectionCount' => 2,
    'useProxy' => true,
    'proxyApi' => getenv('PROXY_API'),
    'proxyMinimumCount' => 100
];