<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'student', 'guard_name' => 'api']);
    }

    public function test_profile_update_resizes_and_stores_uploaded_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'profile@example.com',
        ]);
        $user->assignRole('student');

        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/profile-update', [
                'name' => 'New Name',
                'email' => 'profile@example.com',
                'image' => UploadedFile::fake()->image('avatar.jpg', 2400, 1800),
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'New Name');

        $user->refresh();

        $this->assertNotNull($user->image);
        $this->assertTrue(Storage::disk('public')->exists($user->image));
        $this->assertStringEndsWith('.webp', $user->image);
    }

    public function test_profile_update_rejects_non_image_upload(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'email' => 'profile2@example.com',
        ]);
        $user->assignRole('student');

        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/profile-update', [
                'name' => 'New Name',
                'email' => 'profile2@example.com',
                'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', false);
    }
}
