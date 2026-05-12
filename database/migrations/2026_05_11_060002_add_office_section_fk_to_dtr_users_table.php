<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOfficeSectionFkToDtrUsersTable extends Migration
{
    public function up()
    {
        Schema::table('dtr_users', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->nullable()->after('office');
            $table->unsignedBigInteger('section_id')->nullable()->after('section');
            $table->foreign('office_id')->references('id')->on('offices')->nullOnDelete();
            $table->foreign('section_id')->references('id')->on('sections')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('dtr_users', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['section_id']);
            $table->dropColumn(['office_id', 'section_id']);
        });
    }
}
