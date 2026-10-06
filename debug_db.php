<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
var_dump(config("database.connections.mysql.host"), env("DB_HOST"));

