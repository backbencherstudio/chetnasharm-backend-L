<?php

use App\Models\BasicQuestion;
use App\Models\SpeakingTopic;
use App\Models\User;
use App\Models\Vocabulary;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'student'] as $role) {
        Role::create(['name' => $role, 'guard_name' => 'api']);
    }
});

test('public can view active vocabularies, speaking topics, and basic questions', function () {
    Vocabulary::create([
        'word' => 'Eloquent',
        'meaning' => 'Fluent or persuasive in speaking or writing',
        'example' => 'She gave an eloquent speech.',
        'status' => 1,
    ]);

    SpeakingTopic::create([
        'topic' => 'Climate Change',
        'level' => 'intermediate',
        'status' => 1,
    ]);

    BasicQuestion::create([
        'question' => 'Where are you from?',
        'level' => 'beginner',
        'status' => 1,
    ]);

    $this->getJson('/api/vocabularies')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.word', 'Eloquent');

    $this->getJson('/api/speaking-topics')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.topic', 'Climate Change');

    $this->getJson('/api/basic-questions')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.question', 'Where are you from?');
});

test('admin can perform CRUD on vocabulary', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/vocabularies', [
            'word' => 'Serendipity',
            'meaning' => 'The occurrence of events by chance in a happy way',
            'example' => 'A fortunate stroke of serendipity.',
            'status' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $vocabId = $createRes->json('data.id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/vocabularies/{$vocabId}")
        ->assertOk()
        ->assertJsonPath('data.word', 'Serendipity');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/vocabularies/{$vocabId}", [
            'word' => 'Serendipity Updated',
            'meaning' => 'Updated meaning',
            'status' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.word', 'Serendipity Updated');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/vocabularies/{$vocabId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('vocabularies', ['id' => $vocabId]);
});

test('admin can perform CRUD on speaking topics', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/speaking-topics', [
            'topic' => 'AI in Healthcare',
            'level' => 'advanced',
            'status' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $topicId = $createRes->json('data.id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/speaking-topics/{$topicId}")
        ->assertOk()
        ->assertJsonPath('data.topic', 'AI in Healthcare');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/speaking-topics/{$topicId}", [
            'topic' => 'AI in Medicine',
            'level' => 'advanced',
            'status' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.topic', 'AI in Medicine');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/speaking-topics/{$topicId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('speaking_topics', ['id' => $topicId]);
});

test('admin can perform CRUD on basic questions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $createRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/basic-questions', [
            'question' => 'What is your favorite hobby?',
            'level' => 'beginner',
            'status' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $questionId = $createRes->json('data.id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/admin/basic-questions/{$questionId}")
        ->assertOk()
        ->assertJsonPath('data.question', 'What is your favorite hobby?');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/basic-questions/{$questionId}", [
            'question' => 'What are your favorite hobbies?',
            'level' => 'beginner',
            'status' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.question', 'What are your favorite hobbies?');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/admin/basic-questions/{$questionId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('basic_questions', ['id' => $questionId]);
});
