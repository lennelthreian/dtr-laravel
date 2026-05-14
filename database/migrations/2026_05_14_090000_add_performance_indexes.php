<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->index(['employee_id', 'target_date', 'status'], 'idx_edit_requests_emp_date_status');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->index('supervisor_id', 'idx_offices_supervisor_id');
            $table->index('senior_manager_id', 'idx_offices_senior_manager_id');
            $table->index('oic_id', 'idx_offices_oic_id');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->index('supervisor_id', 'idx_sections_supervisor_id');
        });

        Schema::table('global_holidays', function (Blueprint $table) {
            $table->index('target_date', 'idx_global_holidays_target_date');
        });

        Schema::table('dtr_users', function (Blueprint $table) {
            $table->index('is_active', 'idx_dtr_users_is_active');
            $table->index('office_id', 'idx_dtr_users_office_id');
            $table->index('section_id', 'idx_dtr_users_section_id');
            $table->index(['first_name', 'last_name'], 'idx_dtr_users_name');
        });
    }

    public function down()
    {
        Schema::table('dtr_edit_requests', function (Blueprint $table) {
            $table->dropIndex('idx_edit_requests_emp_date_status');
        });
        Schema::table('offices', function (Blueprint $table) {
            $table->dropIndex('idx_offices_supervisor_id');
            $table->dropIndex('idx_offices_senior_manager_id');
            $table->dropIndex('idx_offices_oic_id');
        });
        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex('idx_sections_supervisor_id');
        });
        Schema::table('global_holidays', function (Blueprint $table) {
            $table->dropIndex('idx_global_holidays_target_date');
        });
        Schema::table('dtr_users', function (Blueprint $table) {
            $table->dropIndex('idx_dtr_users_is_active');
            $table->dropIndex('idx_dtr_users_office_id');
            $table->dropIndex('idx_dtr_users_section_id');
            $table->dropIndex('idx_dtr_users_name');
        });
    }
}
