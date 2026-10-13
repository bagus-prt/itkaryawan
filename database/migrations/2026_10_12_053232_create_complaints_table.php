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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fuel_station_id')
                ->constrained('fuel_stations')
                ->restrictOnDelete();

            $table->string('reporter_name', 150)->nullable();
            $table->string('reporter_contact', 100)->nullable();
            $table->string('category', 100);
            $table->timestamp('incident_at')->nullable();
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('baru');

            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('resolution_notes')->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['fuel_station_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }

};
