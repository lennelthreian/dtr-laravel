<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletionOfRequestIdToDtrEditRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('deletion_of_request_id')->nullable()->after('ls_no_return');
            $table->foreign('deletion_of_request_id')->references('id')->on('dtr_edit_requests')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->dropForeign(['deletion_of_request_id']);
            $table->dropColumn('deletion_of_request_id');
        });
    }
}
