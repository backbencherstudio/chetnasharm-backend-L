<?php

namespace Database\Seeders;

use App\Models\BasicQuestion;
use App\Models\SpeakingTopic;
use App\Models\Vocabulary;
use Database\Seeders\Concerns\GeneratesSeedData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentCatalogSeeder extends Seeder
{
    use GeneratesSeedData;
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
                'meaning' => $this->seedSentence(8),
                'example' => $this->seedSentence(12),
                'pronunciation' => '/'.$this->seedLexify('???-???').'/',
                'part_of_speech' => $this->seedPick(['noun', 'verb', 'adjective', 'adverb', 'phrase']),
                'image' => null,
                'status' => $this->seedBool(90) ? 1 : 0,
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
                'topic' => $this->seedSentence(6).' (#'.$i.')',
                'level' => $this->seedPick($levels),
                'status' => $this->seedBool(90) ? 1 : 0,
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
                'question' => rtrim($this->seedSentence(10), '.').'?',
                'level' => $this->seedPick($levels),
                'status' => $this->seedBool(90) ? 1 : 0,
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
