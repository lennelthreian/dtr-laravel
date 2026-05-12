<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDtrUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dtr_users', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code', 20)->unique();
            $table->string('first_name', 100)->default('');
            $table->string('last_name', 100)->default('');
            $table->string('middle_name', 100)->default('');
            $table->string('position', 200)->default('');
            $table->string('department', 200)->default('');
            $table->string('office', 200)->default('');
            $table->string('employee_status', 50)->default('Regular');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dtr_users');
    }
}
