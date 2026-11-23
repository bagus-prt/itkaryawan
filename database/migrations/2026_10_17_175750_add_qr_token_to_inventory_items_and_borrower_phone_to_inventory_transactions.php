<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->uuid('qr_token')->nullable()->unique();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('borrower_phone', 30)->nullable()->after('division');
            $table->index(['borrower_phone', 'type']);
        });

        DB::table('inventory_items')
            ->select('id')
            ->whereNull('qr_token')
            ->orderBy('id')
            ->chunkById(250, function ($items): void {
                foreach ($items as $item) {
                    DB::table('inventory_items')
                        ->where('id', $item->id)
                        ->update(['qr_token' => (string) Str::uuid()]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['borrower_phone', 'type']);
            $table->dropColumn('borrower_phone');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
