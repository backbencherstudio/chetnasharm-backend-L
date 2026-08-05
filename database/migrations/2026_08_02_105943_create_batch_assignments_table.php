<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->decimal('total_marks', 8, 2)->default(100);
            $table->timestamps();

            $table->index(['batch_id', 'created_at']);
            $table->index(['teacher_id', 'batch_id']);
            $table->index(['batch_id', 'starts_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_assignments');
    }
};
