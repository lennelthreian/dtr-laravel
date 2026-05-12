<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOfficeSectionSuperToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('office', 200)->nullable()->after('emp_code');
            $table->string('section', 200)->nullable()->after('office');
            $table->boolean('is_super')->default(false)->after('section');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['office', 'section', 'is_super']);
        });
    }
}
