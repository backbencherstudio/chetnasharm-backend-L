<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
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
                    'about' => 'Passionate spoken English trainer helping learners build real-world confidence.',
                    'specializations' => ['Spoken English', 'Pronunciation'],
                    'languages_spoken' => ['English', 'Bengali'],
                    'courses_can_teach' => ['Spoken English Masterclass'],
                    'interests' => ['Public speaking', 'Travel'],
                ],
            ],
            [
                'user' => ['name' => 'David Hossain', 'email' => 'david@gmail.com', 'mobile' => '01710000002'],
                'teacher' => [
                    'qualification' => 'IELTS Certified Trainer',
                    'expertise' => 'IELTS Preparation',
                    'years_of_exp' => 6,
                    'bio' => 'IELTS expert focused on high band score strategies.',
                    'about' => 'IELTS expert focused on high band score strategies and exam confidence.',
                    'specializations' => ['IELTS', 'Academic Writing'],
                    'languages_spoken' => ['English', 'Bengali'],
                    'courses_can_teach' => ['IELTS Preparation Course'],
                    'interests' => ['Exam strategy', 'Reading'],
                ],
            ],
            [
                'user' => ['name' => 'Nusrat Jahan', 'email' => 'nusrat@gmail.com', 'mobile' => '01710000003'],
                'teacher' => [
                    'qualification' => 'MBA, Business Communication Specialist',
                    'expertise' => 'Business English',
                    'years_of_exp' => 5,
                    'bio' => 'Helps professionals master English for the corporate world.',
                    'about' => 'Helps professionals master English for meetings, emails, and negotiations.',
                    'specializations' => ['Business English', 'Email writing'],
                    'languages_spoken' => ['English', 'Bengali'],
                    'courses_can_teach' => ['Business English'],
                    'interests' => ['Corporate training', 'Networking'],
                ],
            ],
        ];

        foreach ($teachers as $data) {
            $teacherUser = User::updateOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'department' => 'Teaching',
                    'mobile' => $data['user']['mobile'],
                    'password' => '12345678',
                    'suspend_status' => 0,
                ]
            );
            $teacherUser->assignRole($teacherRole);

            Teacher::updateOrCreate(
                ['user_id' => $teacherUser->id],
                $data['teacher']
            );
        }

        $classes = [
            [
                'title' => 'Spoken English Masterclass',
                'description' => 'A complete course to build your confidence in speaking English fluently in daily life.',
                'short_description' => 'Speak English fluently and confidently.',
                'who_is_for' => 'Beginners to intermediate learners who want to improve their speaking skills.',
                'curriculum' => [
                    [
                        'title' => 'Speaking Foundations',
                        'keypoints' => [
                            'Grammar basics',
                            'Daily conversation practice',
                            'Pronunciation drills',
                            'Public speaking',
                        ],
                    ],
                ],
                'price' => 3000,
                'duration_in_days' => 90,
                'total_classes' => 24,
                'is_class_recording' => 1,
            ],
            [
                'title' => 'IELTS Preparation Course',
                'description' => 'Comprehensive IELTS training covering all four modules with mock tests and feedback.',
                'short_description' => 'Get your desired IELTS band score.',
                'who_is_for' => 'Students planning to study abroad or migrate.',
                'curriculum' => [
                    [
                        'title' => 'IELTS Modules',
                        'keypoints' => [
                            'Listening',
                            'Reading',
                            'Writing',
                            'Speaking',
                        ],
                    ],
                    [
                        'title' => 'Practice & Feedback',
                        'keypoints' => [
                            'Mock tests',
                            'One-on-one feedback',
                        ],
                    ],
                ],
                'price' => 5000,
                'duration_in_days' => 120,
                'total_classes' => 30,
                'is_class_recording' => 1,
            ],
            [
                'title' => 'Business English',
                'description' => 'English communication skills for professionals: emails, meetings, presentations and negotiations.',
                'short_description' => 'Excel in the corporate world with professional English.',
                'who_is_for' => 'Professionals and job seekers who need English at work.',
                'curriculum' => [
                    [
                        'title' => 'Workplace Communication',
                        'keypoints' => [
                            'Business writing',
                            'Meetings',
                            'Presentations',
                            'Negotiation skills',
                            'Email etiquette',
                        ],
                    ],
                ],
                'price' => 4000,
                'duration_in_days' => 60,
                'total_classes' => 18,
                'is_class_recording' => 0,
            ],
        ];

        foreach ($classes as $index => $classData) {
            $class = ClassModel::updateOrCreate(
                ['title' => $classData['title']],
                $classData
            );

            Batch::updateOrCreate(
                ['name' => $class->title.' - Batch 1'],
                [
                    'class_id' => $class->id,
                    'teacher_id' => $index + 1,
                    'total_seat' => 20,
                    'filled_seat' => 12,
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
            ['name' => 'Rakib Hasan', 'email' => 'rakib@gmail.com', 'mobile' => '01720000003'],
            ['name' => 'Farhana Islam', 'email' => 'farhana@gmail.com', 'mobile' => '01720000004'],
            ['name' => 'Shakil Khan', 'email' => 'shakil@gmail.com', 'mobile' => '01720000005'],
            ['name' => 'Priya Das', 'email' => 'priya@gmail.com', 'mobile' => '01720000006'],
        ];

        foreach ($students as $student) {
            $studentUser = User::updateOrCreate(
                ['email' => $student['email']],
                [
                    'name' => $student['name'],
                    'department' => 'Student',
                    'mobile' => $student['mobile'],
                    'password' => '12345678',
                ]
            );
            $studentUser->assignRole($studentRole);
        }
    }
}
