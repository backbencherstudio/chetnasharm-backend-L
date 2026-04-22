<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('class_date');
            $table->enum('status', ['present', 'absent']);
            $table->timestamps();

            $table->unique(['batch_id', 'user_id', 'class_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
