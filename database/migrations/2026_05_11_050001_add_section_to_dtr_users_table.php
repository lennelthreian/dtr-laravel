<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSectionToDtrUsersTable extends Migration
{
    public function up()
    {
        Schema::table('dtr_users', function (Blueprint $table) {
            $table->string('section', 200)->default('')->after('office');
        });
    }

    public function down()
    {
        Schema::table('dtr_users', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
}
