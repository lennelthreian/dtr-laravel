<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Config::set("database.connections.mysql.host", "192.168.1.151");
\Config::set("database.connections.mysql.port", "3306");
try {
    $pdo = new PDO("mysql:host=192.168.1.151;port=3306;dbname=dtr_system", "root", "");
    echo "ok";
} catch (Exception $e) { echo $e->getMessage(); }

