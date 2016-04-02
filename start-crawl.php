<?php
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/18
 * Time: 下午8:16
 */

require __DIR__ . '/vendor/autoload.php';

use App\Spider;

date_default_timezone_set('Asia/Shanghai');

$env = new Dotenv\Dotenv(__DIR__);
$env->load();

echo "crawler start\n";
$startTime = time();

$spider = new Spider();
$spider->createDatabase();
$spider->startToCrawl();

echo "crawler finished\n";
$finishTime = time();

$cost = $finishTime - $startTime;
echo "cost {$cost} seconds\n";
die();