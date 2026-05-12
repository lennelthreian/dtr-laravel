<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDtrEditRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('dtr_edit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('dtr_users')->cascadeOnDelete();
            $table->date('target_date');
            $table->string('field'); // am_in, am_out, pm_in, pm_out
            $table->string('old_value')->nullable();
            $table->string('new_value');
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('reviewer_id')->nullable()->constrained('dtr_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dtr_edit_requests');
    }
}
