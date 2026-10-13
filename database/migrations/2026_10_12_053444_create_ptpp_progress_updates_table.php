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
        Schema::create('ptpp_progress_updates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ptpp_request_id')
                ->constrained('ptpp_requests')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedTinyInteger('progress_percent');
            $table->text('notes');
            $table->string('photo_path')->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->timestamps();

            $table->index(['ptpp_request_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptpp_progress_updates');
    }

};
