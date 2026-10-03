<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        $teacherRole = Role::where('name', 'teacher')->where('guard_name', 'api')->first();
        $studentRole = Role::where('name', 'student')->where('guard_name', 'api')->first();

        $teachers = [
            [
                'user' => ['name' => 'Sarah Rahman', 'email' => 'sarah@gmail.com', 'mobile' => '01710000001'],
                'teacher' => [
                    'qualification' => 'M.A. in English Literature',
                    'expertise' => 'Spoken English',
                    'years_of_exp' => 8,
                    'bio' => 'Passionate spoken English trainer with 8 years of experience.',
                    'specializations' => ['Spoken English', 'Pronunciation'],
                    'languages_spoken' => ['English', 'Bengali'],
                    'courses_can_teach' => ['Spoken English Masterclass'],
                ],
                'class' => [
                    'title' => 'Spoken English Masterclass',
                    'description' => 'A complete course to build your confidence in speaking English fluently in daily life.',
                    'short_description' => 'Speak English fluently and confidently.',
                    'who_is_for' => 'Beginners to intermediate learners who want to improve their speaking skills.',
                    'price' => 3000,
                    'duration_in_days' => 90,
                    'total_classes' => 24,
                    'is_class_recording' => 1,
                ],
            ],
            [
                'user' => ['name' => 'David Hossain', 'email' => 'david@gmail.com', 'mobile' => '01710000002'],
                'teacher' => [
                    'qualification' => 'IELTS Certified Trainer',
                    'expertise' => 'IELTS Preparation',
                    'years_of_exp' => 6,
                    'bio' => 'IELTS expert focused on high band score strategies.',
                    'specializations' => ['IELTS', 'Academic Writing'],
                    'languages_spoken' => ['English', 'Bengali'],
                    'courses_can_teach' => ['IELTS Preparation Course'],
                ],
                'class' => [
                    'title' => 'IELTS Preparation Course',
                    'description' => 'Comprehensive IELTS training covering all four modules with mock tests and feedback.',
                    'short_description' => 'Get your desired IELTS band score.',
                    'who_is_for' => 'Students planning to study abroad or migrate.',
                    'price' => 5000,
                    'duration_in_days' => 120,
                    'total_classes' => 30,
                    'is_class_recording' => 1,
                ],
            ],
        ];

        foreach ($teachers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'department' => 'Teaching',
                    'mobile' => $data['user']['mobile'],
                    'password' => '12345678',
                    'suspend_status' => 0,
                ]
            );
            $user->assignRole($teacherRole);

            $teacher = Teacher::updateOrCreate(
                ['user_id' => $user->id],
                $data['teacher']
            );

            $class = ClassModel::updateOrCreate(
                ['title' => $data['class']['title']],
                $data['class']
            );

            Batch::updateOrCreate(
                ['name' => $class->title.' - Batch 1'],
                [
                    'class_id' => $class->id,
                    'teacher_id' => $teacher->id,
                    'total_seat' => 20,
                    'filled_seat' => 0,
                    'start_date' => now()->addDays(7)->toDateString(),
                    'end_date' => now()->addDays(7 + (int) $class->duration_in_days)->toDateString(),
                    'zoom_link' => 'https://zoom.us/j/'.rand(100000000, 999999999),
                    'status' => 'upcoming',
                    'active_status' => 1,
                ]
            );
        }

        $students = [
            ['name' => 'Tanvir Ahmed', 'email' => 'tanvir@gmail.com', 'mobile' => '01720000001'],
            ['name' => 'Mitu Akter', 'email' => 'mitu@gmail.com', 'mobile' => '01720000002'],
        ];

        foreach ($students as $student) {
            $user = User::updateOrCreate(
                ['email' => $student['email']],
                [
                    'name' => $student['name'],
                    'department' => 'Student',
                    'mobile' => $student['mobile'],
                    'password' => '12345678',
                ]
            );
            $user->assignRole($studentRole);
        }
    }
}
