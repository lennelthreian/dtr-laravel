<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IncreaseHonorificSuffixLength extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('honorific_suffix', 255)->nullable()->change();
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->string('honorific_suffix', 255)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('honorific_suffix', 50)->nullable()->change();
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->string('honorific_suffix', 50)->nullable()->change();
        });
    }
}
