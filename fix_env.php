<?php
// Pre-load before bootstrap
putenv("DB_HOST=192.168.1.151");
putenv("ZK_DB_HOST=192.168.1.151");
$_ENV["DB_HOST"] = "192.168.1.151";
$_ENV["ZK_DB_HOST"] = "192.168.1.151";
$_SERVER["DB_HOST"] = "192.168.1.151";
$_SERVER["ZK_DB_HOST"] = "192.168.1.151";
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Config::set("database.connections.mysql.host", "192.168.1.151");
try {
    \DB::connection("mysql")->getPdo();
    echo "ok";
} catch (Exception $e) { echo $e->getMessage(); }

