<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('class_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->date('class_date');
            $table->text('recording_url');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('class_recordings');
    }
};
