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
        Schema::create('ptpp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ptpp_number', 100)->nullable()->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title', 200);
            $table->text('description');
            $table->string('location', 200)->nullable();
            $table->string('priority', 30)->default('normal');
            $table->string('status', 40)->default('diajukan');
            $table->unsignedTinyInteger('progress_percent')->default(0);

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('completion_submitted_at')->nullable();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptpp_requests');
    }

};
