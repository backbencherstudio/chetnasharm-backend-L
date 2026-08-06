<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $now = now()->toDateTimeString();

        $groups = [
            'users',
            'teachers',
            'classes',
            'batches',
            'payments',
            'settings',
            'content',
            'reports',
        ];

        foreach ($groups as $group) {
            DB::table('permission_groups')->updateOrInsert(
                ['group_name' => $group],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $permissionDefs = [
            ['name' => 'users.view', 'permission_name' => 'View Users', 'permission_type' => 'view', 'group_name' => 'users'],
            ['name' => 'users.create', 'permission_name' => 'Create Users', 'permission_type' => 'create', 'group_name' => 'users'],
            ['name' => 'users.update', 'permission_name' => 'Update Users', 'permission_type' => 'update', 'group_name' => 'users'],
            ['name' => 'users.delete', 'permission_name' => 'Delete Users', 'permission_type' => 'delete', 'group_name' => 'users'],
            ['name' => 'teachers.view', 'permission_name' => 'View Teachers', 'permission_type' => 'view', 'group_name' => 'teachers'],
            ['name' => 'teachers.manage', 'permission_name' => 'Manage Teachers', 'permission_type' => 'manage', 'group_name' => 'teachers'],
            ['name' => 'classes.view', 'permission_name' => 'View Classes', 'permission_type' => 'view', 'group_name' => 'classes'],
            ['name' => 'classes.manage', 'permission_name' => 'Manage Classes', 'permission_type' => 'manage', 'group_name' => 'classes'],
            ['name' => 'batches.view', 'permission_name' => 'View Batches', 'permission_type' => 'view', 'group_name' => 'batches'],
            ['name' => 'batches.manage', 'permission_name' => 'Manage Batches', 'permission_type' => 'manage', 'group_name' => 'batches'],
            ['name' => 'payments.view', 'permission_name' => 'View Payments', 'permission_type' => 'view', 'group_name' => 'payments'],
            ['name' => 'payments.manage', 'permission_name' => 'Manage Payments', 'permission_type' => 'manage', 'group_name' => 'payments'],
            ['name' => 'settings.view', 'permission_name' => 'View Settings', 'permission_type' => 'view', 'group_name' => 'settings'],
            ['name' => 'settings.manage', 'permission_name' => 'Manage Settings', 'permission_type' => 'manage', 'group_name' => 'settings'],
            ['name' => 'content.view', 'permission_name' => 'View Content', 'permission_type' => 'view', 'group_name' => 'content'],
            ['name' => 'content.manage', 'permission_name' => 'Manage Content', 'permission_type' => 'manage', 'group_name' => 'content'],
            ['name' => 'reports.view', 'permission_name' => 'View Reports', 'permission_type' => 'view', 'group_name' => 'reports'],
        ];

        foreach ($permissionDefs as $def) {
            Permission::firstOrCreate(
                ['name' => $def['name'], 'guard_name' => 'api'],
                [
                    'permission_name' => $def['permission_name'],
                    'permission_type' => $def['permission_type'],
                    'group_name' => $def['group_name'],
                ]
            );
        }

        $roles = ['admin', 'teacher', 'student'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'api',
            ]);
        }

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        $teacherRole = Role::where('name', 'teacher')->where('guard_name', 'api')->first();

        if ($adminRole) {
            $adminRole->syncPermissions(Permission::where('guard_name', 'api')->get());
        }

        if ($teacherRole) {
            $teacherRole->syncPermissions(
                Permission::where('guard_name', 'api')
                    ->whereIn('name', ['classes.view', 'batches.view', 'batches.manage', 'content.view', 'reports.view'])
                    ->get()
            );
        }

        Setting::updateOrCreate(
            ['id' => 1],
            [
                'class_time' => 30,
                'class_notify_time' => 30,
                'support_number' => '+8801700000000',
                'support_email' => 'support@chetnasharm.test',
                'social_links' => [
                    'youtube' => 'https://youtube.com/@chetnasharm',
                    'tiktok' => 'https://tiktok.com/@chetnasharm',
                    'instagram' => 'https://instagram.com/chetnasharm',
                    'linkedin' => 'https://linkedin.com/company/chetnasharm',
                    'facebook' => 'https://facebook.com/chetnasharm',
                ],
                'integrations' => Setting::defaultIntegrations(),
            ]
        );
    }
}
