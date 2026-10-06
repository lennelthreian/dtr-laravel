<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
  $pdo = \DB::connection("mysql")->getPdo();
  echo "ok";
} catch (Exception $e) {
  echo $e->getMessage();
}

