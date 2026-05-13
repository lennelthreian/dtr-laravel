<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSystemNameAndLogoSettings extends Migration
{
    public function up()
    {
        DB::table('dtr_settings')->insert([
            [
                'setting_key' => 'system_name',
                'setting_value' => 'MBLISTTDA e-DTR System',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'logo_path',
                'setting_value' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        DB::table('dtr_settings')->whereIn('setting_key', ['system_name', 'logo_path'])->delete();
    }
}
