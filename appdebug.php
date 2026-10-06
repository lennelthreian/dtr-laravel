<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// Try to simulate the DB call that fails
try {
    $conn = app("db");
    $pdo = $conn->connection("mysql")->getPdo();
    echo "ok";
} catch (Exception $e) {
    echo $e->getMessage();
}

