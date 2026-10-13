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
        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('address');
            $table->string('city', 100);
            $table->string('public_qr_token', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['city', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stations');
    }

};
