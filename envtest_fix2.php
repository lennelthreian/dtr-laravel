<?php
require "vendor/autoload.php";
require_once "env_override.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Config::set("database.connections.mysql.host", "192.168.1.151");
var_dump(config("database.connections.mysql.host"));
try {
    \DB::connection("mysql")->getPdo();
    echo "ok";
} catch (Exception $e) { echo $e->getMessage(); }

