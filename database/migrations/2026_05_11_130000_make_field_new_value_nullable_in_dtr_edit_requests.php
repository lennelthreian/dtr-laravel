<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeFieldNewValueNullableInDtrEditRequests extends Migration
{
    public function up()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->string('field', 255)->nullable()->change();
            $table->string('new_value', 255)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->string('field', 255)->nullable(false)->change();
            $table->string('new_value', 255)->nullable(false)->change();
        });
    }
}
