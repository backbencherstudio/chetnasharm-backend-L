<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_activity_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->string('status', 30);
            $table->timestamps();

            $table->index(['teacher_id', 'batch_id', 'student_user_id', 'created_at'], 'student_activity_notes_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_activity_notes');
    }
};
