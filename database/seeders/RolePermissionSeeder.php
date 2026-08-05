<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['admin', 'teacher', 'student'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'api',
            ]);
        }
        Setting::firstOrCreate(
            ['id' => 1],
            [
                'class_time' => 30,
                'class_notify_time' => 30,
                'social_links' => Setting::defaultSocialLinks(),
                'integrations' => Setting::defaultIntegrations(),
            ]
        );
    }
}
