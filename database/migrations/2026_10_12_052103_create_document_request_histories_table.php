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
        Schema::create('document_request_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_request_id')
                ->constrained('document_requests')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 50);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['document_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_histories');
    }

};
