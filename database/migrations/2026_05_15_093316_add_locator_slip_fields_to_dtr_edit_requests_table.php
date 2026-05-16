<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocatorSlipFieldsToDtrEditRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->time('ls_time_left')->nullable()->after('new_value');
            $table->time('ls_time_returned')->nullable()->after('ls_time_left');
            $table->boolean('ls_no_return')->default(false)->after('ls_time_returned');
        });
    }

    public function down()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->dropColumn(['ls_time_left', 'ls_time_returned', 'ls_no_return']);
        });
    }
}
