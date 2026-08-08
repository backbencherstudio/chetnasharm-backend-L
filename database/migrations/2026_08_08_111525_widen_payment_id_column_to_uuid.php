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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_id', 36)->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('payments')->where('payment_id', 'not regexp', '^[0-9a-f]{8}-')->update(['payment_id' => DB::raw('UUID()')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_id', 10)->change();
        });
    }
};
