<?php

namespace Database\Seeders;

use App\Models\BasicQuestion;
use App\Models\SpeakingTopic;
use App\Models\Vocabulary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now()->toDateTimeString();
        $levels = ['beginner', 'elementary', 'intermediate', 'upper_intermediate', 'advanced'];

        $this->seedVocabularies($now, 2000);
        $this->seedSpeakingTopics($now, $levels, 1000);
        $this->seedBasicQuestions($now, $levels, 1000);

        $this->command?->info(sprintf(
            'Content catalog seeded: %d vocabularies, %d speaking topics, %d basic questions',
            Vocabulary::query()->count(),
            SpeakingTopic::query()->count(),
            BasicQuestion::query()->count(),
        ));
    }

    private function seedVocabularies(string $now, int $count): void
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'word' => 'word_'.Str::lower(Str::random(6)).'_'.$i,
                'meaning' => fake()->sentence(8),
                'example' => fake()->sentence(12),
                'pronunciation' => '/'.fake()->lexify('???-???').'/',
                'part_of_speech' => fake()->randomElement(['noun', 'verb', 'adjective', 'adverb', 'phrase']),
                'image' => null,
                'status' => fake()->boolean(90) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('vocabularies')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('vocabularies')->insert($rows);
        }
    }

    /** @param  list<string>  $levels */
    private function seedSpeakingTopics(string $now, array $levels, int $count): void
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'topic' => fake()->sentence(6).' (#'.$i.')',
                'level' => fake()->randomElement($levels),
                'status' => fake()->boolean(90) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('speaking_topics')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('speaking_topics')->insert($rows);
        }
    }

    /** @param  list<string>  $levels */
    private function seedBasicQuestions(string $now, array $levels, int $count): void
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'question' => fake()->sentence(10).'?',
                'level' => fake()->randomElement($levels),
                'status' => fake()->boolean(90) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('basic_questions')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('basic_questions')->insert($rows);
        }
    }
}
