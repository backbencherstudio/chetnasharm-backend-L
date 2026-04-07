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
            $table->string('name', 100);
            $table->string('email', 255)->unique();
            $table->string('mobile', 20)->nullable();
            $table->string('image')->nullable();
            $table->string('intro_video')->nullable();
            $table->string('qualification', 500)->nullable();
            $table->string('expertise')->nullable();
            $table->integer('years_of_exp')->nullable();
            $table->text('bio')->nullable();


            $table->string('zoom_email')->nullable();
            $table->string('zoom_account_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
