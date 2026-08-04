<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpecificTimeToGlobalHolidaysTable extends Migration
{
    public function up()
    {
        Schema::table('global_holidays', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('value');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down()
    {
        Schema::table('global_holidays', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
}
