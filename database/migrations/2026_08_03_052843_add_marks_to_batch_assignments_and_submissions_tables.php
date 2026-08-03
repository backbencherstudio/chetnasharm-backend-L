<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_assignments', function (Blueprint $table) {
            $table->decimal('total_marks', 8, 2)->default(100)->after('due_at');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->decimal('obtained_marks', 8, 2)->nullable()->after('file_path');
            $table->text('feedback')->nullable()->after('obtained_marks');
            $table->timestamp('graded_at')->nullable()->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn(['obtained_marks', 'feedback', 'graded_at']);
        });

        Schema::table('batch_assignments', function (Blueprint $table) {
            $table->dropColumn('total_marks');
        });
    }
};
