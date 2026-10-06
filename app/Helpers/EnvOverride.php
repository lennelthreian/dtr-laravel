<?php

namespace App\Helpers;

class EnvOverride
{
    public static function apply()
    {
        putenv("DB_HOST=192.168.1.151");
        putenv("ZK_DB_HOST=192.168.1.151");
        $_ENV["DB_HOST"] = "192.168.1.151";
        $_ENV["ZK_DB_HOST"] = "192.168.1.151";
        $_SERVER["DB_HOST"] = "192.168.1.151";
        $_SERVER["ZK_DB_HOST"] = "192.168.1.151";
        config(["database.connections.mysql.host" => "192.168.1.151"]);
        config(["database.connections.zkbiotime.host" => "192.168.1.151"]);
        config(["database.connections.mysql.read.host" => "192.168.1.151"]);
        config(["database.connections.mysql.write.host" => "192.168.1.151"]);
    }
}

