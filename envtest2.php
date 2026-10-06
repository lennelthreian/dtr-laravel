<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
var_dump($_ENV["DB_HOST"] ?? "missing", getenv("DB_HOST"));

