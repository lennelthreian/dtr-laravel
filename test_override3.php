<?php
require "vendor/autoload.php";
require_once "app/Helpers/EnvOverride.php";
\App\Helpers\EnvOverride::apply();
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
var_dump(config("database.connections.mysql.host"));
try {
    \DB::connection("mysql")->getPdo();
    echo "ok";
} catch (Exception $e) { echo $e->getMessage(); }

