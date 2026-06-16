<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_monthly_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shared_by');
            $table->integer('month');
            $table->integer('year');
            $table->timestamps();

            $table->unique(['month', 'year']);
            $table->foreign('shared_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_monthly_shares');
    }
};