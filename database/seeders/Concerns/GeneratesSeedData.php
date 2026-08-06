<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Str;

trait GeneratesSeedData
{
    /** @var list<string> */
    private array $firstNames = [
        'Ayesha', 'Rahim', 'Nusrat', 'Karim', 'Farhana', 'Imran', 'Sadia', 'Tanvir',
        'Mitu', 'Rakib', 'Priya', 'Shakil', 'Nadia', 'Hasan', 'Lamia', 'Arif',
        'Sarah', 'David', 'Emma', 'Omar', 'Zara', 'Fahim', 'Rina', 'Jamal',
    ];

    /** @var list<string> */
    private array $lastNames = [
        'Ahmed', 'Hossain', 'Rahman', 'Islam', 'Khan', 'Akter', 'Chowdhury', 'Das',
        'Ali', 'Begum', 'Hasan', 'Karim', 'Sultana', 'Mia', 'Uddin', 'Roy',
    ];

    /** @var list<string> */
    private array $words = [
        'english', 'speaking', 'practice', 'grammar', 'fluency', 'listening', 'reading',
        'writing', 'vocabulary', 'pronunciation', 'confidence', 'conversation', 'lesson',
        'module', 'student', 'teacher', 'batch', 'class', 'progress', 'feedback',
        'homework', 'review', 'session', 'skills', 'learning', 'training', 'course',
    ];

    private function seedName(): string
    {
        return $this->firstNames[array_rand($this->firstNames)].' '.$this->lastNames[array_rand($this->lastNames)];
    }

    private function seedWords(int $count = 3): string
    {
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $picked[] = $this->words[array_rand($this->words)];
        }

        return implode(' ', $picked);
    }

    private function seedSentence(int $wordCount = 8): string
    {
        return Str::ucfirst($this->seedWords($wordCount)).'.';
    }

    private function seedParagraph(): string
    {
        return $this->seedSentence(12).' '.$this->seedSentence(10).' '.$this->seedSentence(9);
    }

    private function seedPick(array $options): mixed
    {
        return $options[array_rand($options)];
    }

    private function seedBool(int $truePercent = 50): bool
    {
        return random_int(1, 100) <= $truePercent;
    }

    private function seedNumber(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    private function seedFloat(float $min, float $max): float
    {
        return round($min + (mt_rand() / mt_getrandmax()) * ($max - $min), 2);
    }

    private function seedCountry(): string
    {
        return $this->seedPick(['Bangladesh', 'India', 'Pakistan', 'Nepal', 'Sri Lanka', 'USA', 'UK', 'Canada']);
    }

    private function seedTimezone(): string
    {
        return $this->seedPick(['Asia/Dhaka', 'Asia/Kolkata', 'Asia/Karachi', 'UTC', 'America/New_York', 'Europe/London']);
    }

    private function seedUrl(): string
    {
        return 'https://example.com/'.Str::lower(Str::random(8));
    }

    private function seedIpv4(): string
    {
        return random_int(1, 255).'.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254);
    }

    private function seedUserAgent(): string
    {
        return $this->seedPick([
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) Firefox/123.0',
        ]);
    }

    private function seedDigits(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= (string) random_int(0, 9);
        }

        return $out;
    }

    private function seedLexify(string $pattern = '???-???'): string
    {
        return preg_replace_callback('/\?/', fn () => chr(random_int(97, 122)), $pattern) ?? $pattern;
    }
}
