<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('department')->nullable();
            $table->string('work_location')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('reviewer_name_snapshot')->nullable();
            $table->unsignedSmallInteger('leave_entitlement_year')->nullable();
            $table->unsignedSmallInteger('leave_entitlement_days')->nullable();
            $table->unsignedSmallInteger('leave_taken_days')->nullable();
            $table->string('transportation', 100)->nullable();
            $table->string('expense_paid_by', 100)->nullable();
            $table->date('return_to_work_date')->nullable();
            $table->date('leave_due_date')->nullable();
            $table->json('companions')->nullable();
            $table->string('employee_signature_path')->nullable();
            $table->string('manager_signature_path')->nullable();
            $table->index(
                ['user_id', 'type', 'created_at'],
                'document_requests_user_type_created_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropIndex('document_requests_user_type_created_at_index');
            $table->dropColumn([
                'department',
                'work_location',
                'employee_name',
                'reviewer_name_snapshot',
                'leave_entitlement_year',
                'leave_entitlement_days',
                'leave_taken_days',
                'transportation',
                'expense_paid_by',
                'return_to_work_date',
                'leave_due_date',
                'companions',
                'employee_signature_path',
                'manager_signature_path',
            ]);
        });
    }
};
