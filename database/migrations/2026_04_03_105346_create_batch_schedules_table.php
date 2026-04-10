<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('batch_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('batch_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                  ->constrained('teachers')
                  ->cascadeOnDelete();

            $table->tinyInteger('day_of_week'); // 0 (Sunday) - 6 (Saturday)
            $table->time('start_time');
            $table->time('end_time');

            $table->timestamps();

            $table->unique(
                ['batch_id', 'teacher_id', 'day_of_week', 'start_time'],
                'batch_sched_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_schedules');
    }
};
