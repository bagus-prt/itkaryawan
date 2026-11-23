<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ptpp_requests', function (Blueprint $table): void {
            $table->foreignId('recipient_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('to_function', 200)->nullable();
            $table->string('from_function', 200)->nullable();
            $table->string('sender_signature_name')->nullable();
            $table->string('sender_short_name', 80)->nullable();
            $table->string('source_category', 50)->nullable();
            $table->string('illustration_path')->nullable();
            $table->string('approval_status', 40)->default('menunggu_persetujuan');
            $table->string('work_status', 40)->default('menunggu_diterima');
            $table->string('reviewer_name_snapshot')->nullable();
            $table->timestamp('work_received_at')->nullable();

            $table->index(
                ['recipient_id', 'approval_status', 'work_status'],
                'ptpp_requests_recipient_workflow_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ptpp_requests', function (Blueprint $table): void {
            $table->dropIndex('ptpp_requests_recipient_workflow_index');
            $table->dropForeign(['recipient_id']);
            $table->dropColumn([
                'recipient_id',
                'to_function',
                'from_function',
                'sender_signature_name',
                'sender_short_name',
                'source_category',
                'illustration_path',
                'approval_status',
                'work_status',
                'reviewer_name_snapshot',
                'work_received_at',
            ]);
        });
    }
};
