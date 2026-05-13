<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFdwwWeeksSetting extends Migration
{
    public function up()
    {
        DB::table('dtr_settings')->insert([
            'setting_key' => 'fdww_weeks',
            'setting_value' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('dtr_settings')->where('setting_key', 'fdww_weeks')->delete();
    }
}
