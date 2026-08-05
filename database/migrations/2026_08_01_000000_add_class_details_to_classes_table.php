<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->text('short_description')->nullable()->after('description');
            $table->text('who_is_for')->nullable()->after('short_description');
            $table->json('curriculum')->nullable()->after('who_is_for');
            $table->tinyInteger('is_class_recording')->default(0)->after('curriculum');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'who_is_for', 'curriculum', 'is_class_recording']);
        });
    }
};
