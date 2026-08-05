<?php

use App\Models\ClassModel;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'admin', 'guard_name' => 'api']);
});

test('admin can create class with structured curriculum', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $curriculum = [
        [
            'title' => 'Week 1',
            'keypoints' => ['Introductions', 'Basic grammar'],
        ],
        [
            'title' => 'Week 2',
            'keypoints' => ['Conversation practice'],
        ],
    ];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/classes', [
            'title' => 'Structured Class',
            'description' => 'Desc',
            'curriculum' => $curriculum,
            'price' => 1000,
            'duration_in_days' => 30,
            'total_classes' => 10,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.curriculum.0.title', 'Week 1')
        ->assertJsonPath('data.curriculum.0.keypoints.0', 'Introductions')
        ->assertJsonPath('data.curriculum.1.title', 'Week 2');

    $class = ClassModel::query()->where('title', 'Structured Class')->first();

    expect($class)->not->toBeNull()
        ->and($class->curriculum)->toBe($curriculum);
});

test('admin can update class curriculum structure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $class = ClassModel::create([
        'title' => 'Existing Class',
        'description' => 'Desc',
        'price' => 1000,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
        'curriculum' => [
            [
                'title' => 'Old',
                'keypoints' => ['A'],
            ],
        ],
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/admin/classes/{$class->id}", [
            'title' => 'Existing Class',
            'curriculum' => [
                [
                    'title' => 'Updated Module',
                    'keypoints' => ['Point 1', 'Point 2'],
                ],
            ],
            'price' => 1000,
            'duration_in_days' => 30,
            'total_classes' => 10,
        ])
        ->assertOk()
        ->assertJsonPath('data.curriculum.0.title', 'Updated Module')
        ->assertJsonPath('data.curriculum.0.keypoints.1', 'Point 2');
});

test('public class responses return structured curriculum', function () {
    $class = ClassModel::create([
        'title' => 'Public Curriculum Class',
        'description' => 'Desc',
        'price' => 1000,
        'duration_in_days' => 30,
        'total_classes' => 10,
        'is_active' => 1,
        'curriculum' => [
            [
                'title' => 'Module A',
                'keypoints' => ['Key 1', 'Key 2'],
            ],
        ],
    ]);

    $this->getJson('/api/classes')
        ->assertOk()
        ->assertJsonPath('data.0.id', $class->id)
        ->assertJsonPath('data.0.curriculum.0.title', 'Module A')
        ->assertJsonPath('data.0.curriculum.0.keypoints.0', 'Key 1');

    $this->getJson("/api/single-class/{$class->id}")
        ->assertOk()
        ->assertJsonPath('data.curriculum.0.title', 'Module A')
        ->assertJsonPath('data.curriculum.0.keypoints.1', 'Key 2');
});

test('curriculum validation requires title and keypoints', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/admin/classes', [
            'title' => 'Invalid Curriculum',
            'price' => 1000,
            'duration_in_days' => 30,
            'total_classes' => 10,
            'curriculum' => [
                [
                    'title' => 'Missing keypoints',
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['curriculum.0.keypoints']);
});
