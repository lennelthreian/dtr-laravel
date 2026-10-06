<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo = \DB::connection("mysql")->getPdo();
    echo "ok";
} catch (PDOException $e) {
    echo "PDO:".$e->getCode().":".$e->getMessage();
} catch (Exception $e) {
    echo "EX:".$e->getCode().":".$e->getMessage();
}

