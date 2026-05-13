<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFourDayWorkWeekSettings extends Migration
{
    public function up()
    {
        DB::table('dtr_settings')->insert([
            [
                'setting_key' => 'four_day_work_week',
                'setting_value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'fdww_am_start',
                'setting_value' => '07:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'fdww_am_end',
                'setting_value' => '12:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'fdww_pm_start',
                'setting_value' => '13:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'fdww_pm_end',
                'setting_value' => '19:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'fdww_am_start_flexi',
                'setting_value' => '60',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'fdww_pm_end_flexi',
                'setting_value' => '60',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        DB::table('dtr_settings')->whereIn('setting_key', [
            'four_day_work_week',
            'fdww_am_start',
            'fdww_am_end',
            'fdww_pm_start',
            'fdww_pm_end',
            'fdww_am_start_flexi',
            'fdww_pm_end_flexi',
        ])->delete();
    }
}
