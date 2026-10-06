<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = app("db");
$conn = $db->connection("mysql");
$cfg = $conn->getConfig();
var_dump($cfg["host"]);

