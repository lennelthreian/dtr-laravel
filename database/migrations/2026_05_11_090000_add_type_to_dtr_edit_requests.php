<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToDtrEditRequests extends Migration
{
    public function up()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->string('type', 30)->default('time_correction')->after('employee_id');
        });
    }

    public function down()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
