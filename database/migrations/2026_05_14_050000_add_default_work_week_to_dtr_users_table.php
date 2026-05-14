<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultWorkWeekToDtrUsersTable extends Migration
{
    public function up()
    {
        Schema::table('dtr_users', function (Blueprint $table) {
            $table->string('default_work_week', 10)->default('5-day')->after('employee_status');
        });
    }

    public function down()
    {
        Schema::table('dtr_users', function (Blueprint $table) {
            $table->dropColumn('default_work_week');
        });
    }
}
