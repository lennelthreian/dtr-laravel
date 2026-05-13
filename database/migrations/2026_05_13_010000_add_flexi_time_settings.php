<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFlexiTimeSettings extends Migration
{
    public function up()
    {
        DB::table('dtr_settings')->insert([
            [
                'setting_key' => 'am_start_flexi',
                'setting_value' => '60',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'pm_end_flexi',
                'setting_value' => '60',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        DB::table('dtr_settings')->whereIn('setting_key', ['am_start_flexi', 'pm_end_flexi'])->delete();
    }
}
