<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
var_dump(env("DB_HOST"), config("database.connections.mysql.host"));

