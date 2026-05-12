<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSeniorManagerIdToOfficesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->unsignedBigInteger('senior_manager_id')->nullable()->after('supervisor_id');
        });
    }

    public function down()
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn('senior_manager_id');
        });
    }
}
