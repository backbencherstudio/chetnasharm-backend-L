<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_assignments', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('attachment');
            $table->index(['batch_id', 'starts_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('batch_assignments', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'starts_at', 'due_at']);
            $table->dropColumn('starts_at');
        });
    }
};
