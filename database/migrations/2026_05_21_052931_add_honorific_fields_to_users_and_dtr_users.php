<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHonorificFieldsToUsersAndDtrUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('honorific_prefix', 50)->nullable()->after('last_name');
            $table->string('honorific_suffix', 50)->nullable()->after('honorific_prefix');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->string('honorific_prefix', 50)->nullable()->after('last_name');
            $table->string('honorific_suffix', 50)->nullable()->after('honorific_prefix');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['honorific_prefix', 'honorific_suffix']);
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->dropColumn(['honorific_prefix', 'honorific_suffix']);
        });
    }
}
