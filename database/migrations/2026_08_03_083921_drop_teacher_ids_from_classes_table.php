<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('classes', 'teacher_ids')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropColumn('teacher_ids');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('classes', 'teacher_ids')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->json('teacher_ids')->nullable()->after('curriculum');
            });
        }
    }
};
