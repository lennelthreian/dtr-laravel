<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSectionsTable extends Migration
{
    public function up()
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->timestamps();
            $table->unique(['office_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sections');
    }
}
