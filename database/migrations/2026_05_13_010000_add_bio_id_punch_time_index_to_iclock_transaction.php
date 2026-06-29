<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddBioIdPunchTimeIndexToIclockTransaction extends Migration
{
    public function up()
    {
        try {
            DB::connection('zkbiotime')->statement('
                ALTER TABLE iclock_transaction 
                ADD INDEX idx_bio_id_punch_time (bio_id, punch_time)
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
                DROP INDEX idx_bio_id_punch_time
            ');
        } catch (\Exception $e) {
            // Index may not exist; skip
        }
    }
}
