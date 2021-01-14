<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => QANB_DB_HOST,
    'database'  => QANB_DB_NAME,
    'username'  => QANB_DB_USERNAME,
    'password'  => QANB_DB_PASSWORD,
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_0900_ai_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
