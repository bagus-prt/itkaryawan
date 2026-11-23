<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('fuel_station_id')
                ->nullable()
                ->change();
            $table->string('station_code', 50)->nullable();
            $table->text('station_address')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('complaint_date')->nullable();
            $table->index('complaint_date');
        });

        DB::table('complaints')
            ->whereNull('complaint_date')
            ->update(['complaint_date' => DB::raw('DATE(created_at)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('complaints')->whereNull('fuel_station_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back complaint fields while complaints are not linked to a fuel station.'
            );
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['complaint_date']);
            $table->dropColumn(['station_code', 'station_address', 'created_by', 'complaint_date']);
            $table->foreignId('fuel_station_id')
                ->nullable(false)
                ->change();
        });
    }
};
