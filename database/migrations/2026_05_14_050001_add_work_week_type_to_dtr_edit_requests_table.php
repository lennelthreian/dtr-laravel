<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkWeekTypeToDtrEditRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->string('work_week_type', 10)->nullable()->after('new_value');
        });
    }

    public function down()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->dropColumn('work_week_type');
        });
    }
}
