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
        Schema::create('inventory_transaction_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_transaction_id')
                ->constrained('inventory_transactions')
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();

            $table->foreignId('related_loan_item_id')
                ->nullable()
                ->constrained('inventory_transaction_items')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');
            $table->text('condition_notes')->nullable();

            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index('related_loan_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transaction_items');
    }

};
