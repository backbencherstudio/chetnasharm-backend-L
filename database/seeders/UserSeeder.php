<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchSchedule;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
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
            'sarah' => [
                'user' => ['name' => 'Sarah Rahman', 'email' => 'sarah@gmail.com', 'mobile' => '01710000001'],
                'teacher' => [
                    'qualification' => 'M.A. in English Literature',
                    'expertise' => 'Spoken English & IELTS',
                    'years_of_exp' => 8,
                    'bio' => 'Passionate spoken English and IELTS trainer with 8 years of experience.',
                    'about' => 'Passionate spoken English and IELTS trainer helping learners achieve confidence and fluency.',
                    'country' => 'Bangladesh',
                    'timezone' => 'Asia/Dhaka',
                    'specializations' => ['Spoken English', 'Pronunciation', 'IELTS Speaking'],
                    'languages_spoken' => ['English', 'Bengali'],
                    'courses_can_teach' => ['Spoken English Masterclass', 'IELTS Preparation Course'],
                    'interests' => ['Public speaking', 'Linguistics'],
                ],
            ],
            'david' => [
                'user' => ['name' => 'David Hossain', 'email' => 'david@gmail.com', 'mobile' => '01710000002'],
                'teacher' => [
                    'qualification' => 'IELTS Certified Trainer',
                    'expertise' => 'IELTS Preparation',
                    'years_of_exp' => 6,
                    'bio' => 'IELTS expert focused on high band score strategies.',
                    'about' => 'IELTS expert focused on high band score strategies and complete exam preparation.',
                    'country' => 'Bangladesh',
                    'timezone' => 'Asia/Dhaka',
                    'specializations' => ['IELTS', 'Academic Writing'],
                    'languages_spoken' => ['English', 'Bengali'],
                    'courses_can_teach' => ['IELTS Preparation Course'],
                    'interests' => ['Exam strategies', 'Literature'],
                ],
            ],
        ];

        $teacherModels = [];

        foreach ($teachers as $key => $data) {
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

            $teacherModels[$key] = $teacher;

            foreach (range(0, 6) as $day) {
                TeacherAvailability::updateOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'day_of_week' => $day,
                        'start_time' => '09:00:00',
                    ],
                    [
                        'end_time' => '13:00:00',
                    ]
                );

                TeacherAvailability::updateOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'day_of_week' => $day,
                        'start_time' => '16:00:00',
                    ],
                    [
                        'end_time' => '22:00:00',
                    ]
                );
            }
        }

        $classes = [
            'spoken_english' => [
                'title' => 'Spoken English Masterclass',
                'description' => 'A complete course to build your confidence in speaking English fluently in daily life.',
                'short_description' => 'Speak English fluently and confidently.',
                'who_is_for' => 'Beginners to intermediate learners who want to improve their speaking skills.',
                'price' => 3000,
                'duration_in_days' => 90,
                'total_classes' => 24,
                'is_class_recording' => 1,
            ],
            'ielts' => [
                'title' => 'IELTS Preparation Course',
                'description' => 'Comprehensive IELTS training covering all four modules with mock tests and feedback.',
                'short_description' => 'Get your desired IELTS band score.',
                'who_is_for' => 'Students planning to study abroad or migrate.',
                'price' => 5000,
                'duration_in_days' => 120,
                'total_classes' => 30,
                'is_class_recording' => 1,
            ],
        ];

        $classModels = [];
        foreach ($classes as $key => $classData) {
            $classModels[$key] = ClassModel::updateOrCreate(
                ['title' => $classData['title']],
                $classData
            );
        }

        $batches = [
            [
                'name' => 'Spoken English Masterclass - Batch 1',
                'class_id' => $classModels['spoken_english']->id,
                'teacher_id' => $teacherModels['sarah']->id,
                'total_seat' => 20,
                'filled_seat' => 0,
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(7 + (int) $classModels['spoken_english']->duration_in_days)->toDateString(),
                'zoom_link' => 'https://zoom.us/j/'.rand(100000000, 999999999),
                'status' => 'upcoming',
                'active_status' => 1,
                'schedules' => [
                    ['day_of_week' => 0, 'start_time' => '19:00:00', 'end_time' => '19:30:00'],
                    ['day_of_week' => 2, 'start_time' => '19:00:00', 'end_time' => '19:30:00'],
                    ['day_of_week' => 4, 'start_time' => '19:00:00', 'end_time' => '19:30:00'],
                ],
            ],
            [
                'name' => 'IELTS Preparation Course - Batch 1',
                'class_id' => $classModels['ielts']->id,
                'teacher_id' => $teacherModels['david']->id,
                'total_seat' => 20,
                'filled_seat' => 0,
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(7 + (int) $classModels['ielts']->duration_in_days)->toDateString(),
                'zoom_link' => 'https://zoom.us/j/'.rand(100000000, 999999999),
                'status' => 'upcoming',
                'active_status' => 1,
                'schedules' => [
                    ['day_of_week' => 1, 'start_time' => '18:00:00', 'end_time' => '18:30:00'],
                    ['day_of_week' => 3, 'start_time' => '18:00:00', 'end_time' => '18:30:00'],
                    ['day_of_week' => 5, 'start_time' => '18:00:00', 'end_time' => '18:30:00'],
                ],
            ],
            [
                'name' => 'IELTS Preparation Course - Batch 2',
                'class_id' => $classModels['ielts']->id,
                'teacher_id' => $teacherModels['sarah']->id,
                'total_seat' => 20,
                'filled_seat' => 0,
                'start_date' => now()->addDays(14)->toDateString(),
                'end_date' => now()->addDays(14 + (int) $classModels['ielts']->duration_in_days)->toDateString(),
                'zoom_link' => 'https://zoom.us/j/'.rand(100000000, 999999999),
                'status' => 'upcoming',
                'active_status' => 1,
                'schedules' => [
                    ['day_of_week' => 6, 'start_time' => '20:00:00', 'end_time' => '20:30:00'],
                    ['day_of_week' => 1, 'start_time' => '20:00:00', 'end_time' => '20:30:00'],
                    ['day_of_week' => 3, 'start_time' => '20:00:00', 'end_time' => '20:30:00'],
                ],
            ],
        ];

        foreach ($batches as $batchData) {
            $schedules = $batchData['schedules'];
            unset($batchData['schedules']);

            $batch = Batch::updateOrCreate(
                ['name' => $batchData['name']],
                $batchData
            );

            foreach ($schedules as $schedule) {
                BatchSchedule::updateOrCreate(
                    [
                        'batch_id' => $batch->id,
                        'teacher_id' => $batch->teacher_id,
                        'day_of_week' => $schedule['day_of_week'],
                        'start_time' => $schedule['start_time'],
                    ],
                    [
                        'end_time' => $schedule['end_time'],
                    ]
                );
            }
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
