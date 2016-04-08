<?php
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/1
 * Time: 上午11:40
 */
return [
    'maxConnectionCount' => 10,
    'useProxy'           => true,
    'proxyApi'           => getenv('PROXY_API'),
    'proxyMinimumCount'  => 150,
    'connectTimeOut'     => 5, //Use 0 to wait indefinitely
    'responseTimeOut'    => 5
];