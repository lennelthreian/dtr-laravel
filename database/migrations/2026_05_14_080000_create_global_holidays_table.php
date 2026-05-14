<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlobalHolidaysTable extends Migration
{
    public function up()
    {
        Schema::create('global_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('target_date');
            $table->string('type', 30);
            $table->string('value', 20)->default('whole_day');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique('target_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('global_holidays');
    }
}
