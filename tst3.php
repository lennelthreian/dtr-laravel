<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$manager = app("db");
$manager->purge("mysql");
try {
    $manager->connection("mysql")->getPdo();
    echo "ok";
} catch (Exception $e) {
    echo $e->getMessage();
}

