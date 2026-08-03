<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call(RolePermissionSeeder::class);

        $adminApi = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'department' => 'Administration',
                'mobile' => '01700000001',
                'password' => '12345678',
            ]
        );
        $adminApi->assignRole(Role::where('name', 'admin')->where('guard_name', 'api')->first());

        $this->call(DemoDataSeeder::class);
        $this->call(StudentActivityNoteSeeder::class);
    }
}
