<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $classes = DB::table('classes')
            ->whereNotNull('curriculum')
            ->get(['id', 'curriculum']);

        foreach ($classes as $class) {
            $decoded = json_decode($class->curriculum, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                continue;
            }

            $keypoints = collect(preg_split('/\r\n|\r|\n|,/', (string) $class->curriculum) ?: [])
                ->map(fn (string $item) => trim($item))
                ->filter()
                ->values()
                ->all();

            DB::table('classes')->where('id', $class->id)->update([
                'curriculum' => json_encode([
                    [
                        'title' => 'Curriculum',
                        'keypoints' => $keypoints !== [] ? $keypoints : [(string) $class->curriculum],
                    ],
                ]),
            ]);
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE classes MODIFY curriculum JSON NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE classes MODIFY curriculum TEXT NULL');
        }

        $classes = DB::table('classes')
            ->whereNotNull('curriculum')
            ->get(['id', 'curriculum']);

        foreach ($classes as $class) {
            $decoded = json_decode($class->curriculum, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                continue;
            }

            $text = collect($decoded)
                ->map(function ($section) {
                    $title = $section['title'] ?? '';
                    $keypoints = collect($section['keypoints'] ?? [])->implode(', ');

                    return trim($title.($keypoints !== '' ? ': '.$keypoints : ''));
                })
                ->filter()
                ->implode(' | ');

            DB::table('classes')->where('id', $class->id)->update([
                'curriculum' => $text !== '' ? $text : null,
            ]);
        }
    }
};
