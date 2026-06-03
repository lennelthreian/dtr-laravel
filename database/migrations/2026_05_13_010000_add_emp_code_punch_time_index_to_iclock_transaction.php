<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddEmpCodePunchTimeIndexToIclockTransaction extends Migration
{
    public function up()
    {
        try {
            DB::connection('zkbiotime')->statement('
                ALTER TABLE iclock_transaction 
                ADD INDEX idx_emp_code_punch_time (emp_code, punch_time)
            ');
        } catch (\Exception $e) {
            // Index may already exist; skip
        }
    }

    public function down()
    {
        try {
            DB::connection('zkbiotime')->statement('
                ALTER TABLE iclock_transaction 
                DROP INDEX idx_emp_code_punch_time
            ');
        } catch (\Exception $e) {
            // Index may not exist; skip
        }
    }
}
