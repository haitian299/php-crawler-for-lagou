<?php
/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/3/25
 * Time: 下午1:58
 */
return [

    'default' => 'mysql',

    'connections' => [

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => getenv('DB_HOST'),
            'database'  => getenv('DB_DATABASE'),
            'username'  => getenv('DB_USERNAME'),
            'password'  => getenv('DB_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => getenv('DB_HOST'),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
        ],

        'sqlsrv' => [
            'driver'   => 'sqlsrv',
            'host'     => getenv('DB_HOST'),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'prefix'   => '',
        ],

    ],

    'redis'  => [

        'cluster' => false,

        'default' => [
            'host' => getenv('REDIS_HOST'),
            'port' => getenv('REDIS_PORT'),
        ],

    ],

    /**
     * learn how to write table migrations
     * ->http://laravel-china.org/docs/5.1/migrations#writing-migrations
     */
    'tables' => [
        'lagou_job'     => function ($table) {
            $table->integer('id')->unsigned();
            $table->string('name');
            $table->string('type');
            $table->tinyInteger('salary_min');
            $table->tinyInteger('salary_max');
            $table->string('first_type');
            $table->string('experience_demand');
            $table->string('city');
            $table->string('education_demand');
            $table->integer('company_id');
            $table->string('contract_type');
            $table->string('advantage');
            $table->timestamp('create_time')->nullable();
            $table->string('address');
            $table->longText('detail');
            $table->timestamps();
            $table->primary('id');
        },
        'lagou_company' => function ($table) {
            $table->integer('id')->unsigned();
            $table->string('name');
            $table->string('short_name');
            $table->string('logo');
            $table->string('city');
            $table->string('population');
            $table->tinyInteger('job_process_rate_timely')->unsigned()->nullable();
            $table->tinyInteger('days_cost_to_process')->unsigned()->nullable();
            $table->string('finance_stage');
            $table->string('finance_stage_process')->nullable();
            $table->string('industries');
            $table->string('labels');
            $table->timestamps();
            $table->primary('id');
        },
    ],
];