<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupervisorIdToOfficesSections extends Migration
{
    public function up()
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->unsignedBigInteger('supervisor_id')->nullable()->after('name');
            $table->foreign('supervisor_id')->references('id')->on('dtr_users')->nullOnDelete();
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedBigInteger('supervisor_id')->nullable()->after('name');
            $table->foreign('supervisor_id')->references('id')->on('dtr_users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
        });
    }
}
