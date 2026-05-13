<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSexToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sex', 10)->nullable()->after('position');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->string('sex', 10)->nullable()->after('position');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sex');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->dropColumn('sex');
        });
    }
}
