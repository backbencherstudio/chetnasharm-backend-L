<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaking_topics', function (Blueprint $table) {
            $table->id();
            $table->text('topic');
            $table->string('level')->nullable(); // A1, A2, B1 etc.
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaking_topics');
    }
};
