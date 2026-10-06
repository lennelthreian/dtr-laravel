<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $conn = app("db")->connection("mysql");
    $conn->select("select 1");
    echo "ok";
} catch (Exception $e) {
    echo $e->getMessage();
}

