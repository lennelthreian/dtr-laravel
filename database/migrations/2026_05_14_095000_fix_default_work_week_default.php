<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixDefaultWorkWeekDefault extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE dtr_users MODIFY COLUMN default_work_week VARCHAR(10) DEFAULT NULL');
        DB::table('dtr_users')->where('default_work_week', '5-day')->update(['default_work_week' => null]);
    }

    public function down()
    {
        DB::table('dtr_users')->whereNull('default_work_week')->update(['default_work_week' => '5-day']);
        DB::statement('ALTER TABLE dtr_users MODIFY COLUMN default_work_week VARCHAR(10) DEFAULT "5-day"');
    }
}
