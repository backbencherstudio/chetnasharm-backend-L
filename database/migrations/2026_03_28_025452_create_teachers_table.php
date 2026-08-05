<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('intro_video')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('timezone', 100)->nullable();
            $table->string('qualification', 500)->nullable();
            $table->string('expertise')->nullable();
            $table->integer('years_of_exp')->nullable();
            $table->text('bio')->nullable();
            $table->text('about')->nullable();
            $table->json('specializations')->nullable();
            $table->json('languages_spoken')->nullable();
            $table->json('courses_can_teach')->nullable();
            $table->json('interests')->nullable();
            $table->string('zoom_email')->nullable();
            $table->string('zoom_account_id')->nullable();
            $table->boolean('is_top')->default(false);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
