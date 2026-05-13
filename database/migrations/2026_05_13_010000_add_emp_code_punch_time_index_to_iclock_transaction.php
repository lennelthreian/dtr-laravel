<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddEmpCodePunchTimeIndexToIclockTransaction extends Migration
{
    public function up()
    {
        DB::connection('zkbiotime')->statement('
            ALTER TABLE iclock_transaction 
            ADD INDEX idx_emp_code_punch_time (emp_code, punch_time)
        ');
    }

    public function down()
    {
        DB::connection('zkbiotime')->statement('
            ALTER TABLE iclock_transaction 
            DROP INDEX idx_emp_code_punch_time
        ');
    }
}
