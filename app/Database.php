<?php namespace App;

/**
 * Created by PhpStorm.
 * User: haitian
 * Date: 16/4/11
 * Time: 12:57
 */
use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    public static function boot()
    {
        $database = Config::get('database.default');
        $capsule = new Capsule();
        $capsule->addConnection(Config::get("database.connections.{$database}"));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        static::createDatabase();
    }

    public static function createDatabase()
    {
        foreach (Config::get('database.tables') as $name => $closure) {
            if (!Capsule::schema()->hasTable($name)) {
                Capsule::schema()->create($name, $closure);
            }
        }
    }
}