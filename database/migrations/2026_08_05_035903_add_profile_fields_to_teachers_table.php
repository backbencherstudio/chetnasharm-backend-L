<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->text('about')->nullable()->after('bio');
            $table->json('specializations')->nullable()->after('about');
            $table->json('languages_spoken')->nullable()->after('specializations');
            $table->json('courses_can_teach')->nullable()->after('languages_spoken');
            $table->json('interests')->nullable()->after('courses_can_teach');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn([
                'about',
                'specializations',
                'languages_spoken',
                'courses_can_teach',
                'interests',
            ]);
        });
    }
};
