<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptpp_request_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ptpp_request_id')
                ->constrained('ptpp_requests')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 50);
            $table->string('old_approval_status', 40)->nullable();
            $table->string('new_approval_status', 40)->nullable();
            $table->string('old_work_status', 40)->nullable();
            $table->string('new_work_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ptpp_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptpp_request_histories');
    }
};
