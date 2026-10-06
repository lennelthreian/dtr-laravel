<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config("database.connections.mysql");
var_dump($config["host"]);
$dsn = "mysql:host=" . $config["host"] . ";port=" . $config["port"] . ";dbname=" . $config["database"];
try {
    new PDO($dsn, $config["username"], $config["password"], []);
    echo "ok";
} catch (Exception $e) { echo $e->getMessage(); }

