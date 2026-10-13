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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 100)->unique();
            $table->string('type', 30);
            $table->string('borrower_name', 150)->nullable();
            $table->string('division', 100)->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 30)->default('posted');
            $table->text('notes')->nullable();
            $table->timestamp('transaction_at')->useCurrent();

            $table->timestamps();

            $table->index(['type', 'transaction_at']);
            $table->index(['borrower_name', 'transaction_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }

};
