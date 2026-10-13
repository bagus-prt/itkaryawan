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
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('type', 30);
            $table->string('letter_number', 100)->nullable()->unique();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('destination')->nullable();
            $table->text('reason');

            $table->string('status', 30)->default('diajukan');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('pdf_path')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }

};
