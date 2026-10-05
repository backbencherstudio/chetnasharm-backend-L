<?php

use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
});

test('user seeder creates sarah with ielts batch and weekly schedules', function (): void {
    $sarahUser = User::where('email', 'sarah@gmail.com')->first();
    expect($sarahUser)->not->toBeNull();

    $sarahTeacher = Teacher::where('user_id', $sarahUser->id)->first();
    expect($sarahTeacher)->not->toBeNull();
    expect($sarahTeacher->courses_can_teach)->toContain('IELTS Preparation Course');

    $ieltsClass = ClassModel::where('title', 'IELTS Preparation Course')->first();
    expect($ieltsClass)->not->toBeNull();

    $sarahBatch = Batch::where('name', 'IELTS Preparation Course - Batch 2')->first();
    expect($sarahBatch)->not->toBeNull()
        ->and($sarahBatch->class_id)->toBe($ieltsClass->id)
        ->and($sarahBatch->teacher_id)->toBe($sarahTeacher->id);

    $schedules = BatchSchedule::where('batch_id', $sarahBatch->id)->get();
    expect($schedules)->toHaveCount(3);

    $availabilities = TeacherAvailability::where('teacher_id', $sarahTeacher->id)->get();
    expect($availabilities->count())->toBeGreaterThan(0);
});

test('landing batches endpoint for ielts returns batches with sarah and weekly schedules', function (): void {
    $ieltsClass = ClassModel::where('title', 'IELTS Preparation Course')->first();

    $response = $this->getJson("/api/batches/{$ieltsClass->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data'));
    $sarahBatchItem = $items->firstWhere('name', 'IELTS Preparation Course - Batch 2');

    expect($sarahBatchItem)->not->toBeNull()
        ->and($sarahBatchItem['teacher']['name'])->toBe('Sarah Rahman')
        ->and($sarahBatchItem['schedules'])->toHaveCount(3);
});

test('user seeder is idempotent and can be run multiple times', function (): void {
    $this->seed(UserSeeder::class);

    expect(Batch::where('name', 'IELTS Preparation Course - Batch 2')->count())->toBe(1);
    expect(BatchSchedule::where('batch_id', Batch::where('name', 'IELTS Preparation Course - Batch 2')->value('id'))->count())->toBe(3);
});
