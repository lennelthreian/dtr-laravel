<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOicIdToOfficesTable extends Migration
{
    public function up()
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->unsignedBigInteger('oic_id')->nullable()->after('senior_manager_id');
            $table->foreign('oic_id')->references('id')->on('dtr_users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropForeign(['oic_id']);
            $table->dropColumn('oic_id');
        });
    }
}
