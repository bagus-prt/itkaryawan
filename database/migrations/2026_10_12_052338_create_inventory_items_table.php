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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('inventory_categories')
                ->restrictOnDelete();

            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('unit', 30);
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['stock_on_hand', 'minimum_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }

};
