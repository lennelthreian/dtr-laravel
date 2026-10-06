<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Config::set("database.connections.mysql.host", "host.docker.internal");
\Config::set("database.connections.mysql.port", "3306");
try {
    \DB::connection("mysql")->getPdo();
    echo "ok";
} catch (Exception $e) {
    echo $e->getMessage();
}

