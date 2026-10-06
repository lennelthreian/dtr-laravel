<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require_once "app/Helpers/EnvOverride.php";
\App\Helpers\EnvOverride::apply();
var_dump(config("database.connections.mysql.host"));
try {
    \DB::connection("mysql")->getPdo();
    echo "ok";
} catch (Exception $e) { echo $e->getMessage(); }

