<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameEmpCodeToBioId extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('emp_code', 'bio_id');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->renameColumn('emp_code', 'bio_id');
        });

        try {
            DB::connection('zkbiotime')->statement('
                ALTER TABLE iclock_transaction 
                CHANGE COLUMN emp_code bio_id VARCHAR(20)
            ');
        } catch (\Exception $e) {
            // Column may not exist or different connection setup
        }
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('bio_id', 'emp_code');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->renameColumn('bio_id', 'emp_code');
        });

        try {
            DB::connection('zkbiotime')->statement('
                ALTER TABLE iclock_transaction 
                CHANGE COLUMN bio_id emp_code VARCHAR(20)
            ');
        } catch (\Exception $e) {
            // Column may not exist or different connection setup
        }
    }
}
