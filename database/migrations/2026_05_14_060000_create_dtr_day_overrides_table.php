<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDtrDayOverridesTable extends Migration
{
    public function up()
    {
        Schema::create('dtr_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('target_date');
            $table->string('work_week_type', 10);
            $table->timestamps();

            $table->unique(['employee_id', 'target_date']);
            $table->foreign('employee_id')->references('id')->on('dtr_users')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dtr_day_overrides');
    }
}
