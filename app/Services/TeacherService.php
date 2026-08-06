<?php

namespace App\Services;

use App\Common\Pagination;
use App\Common\PhoneNormalizer;
use App\Models\Batch;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class TeacherService
{
    public function paginateForAdmin(Request $request): LengthAwarePaginator
    {
        $perPage = Pagination::perPage($request);

        $query = Teacher::query()->with('user');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('bio', 'like', "%{$search}%")
                    ->orWhere('expertise', 'like', "%{$search}%")
                    ->orWhere('qualification', 'like', "%{$search}%")
                    ->orWhere('about', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('expertise')) {
            $query->where('expertise', 'like', "%{$request->expertise}%");
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{teacher: Teacher, user: User, password: string}
     */
    public function create(array $validated, ?Image $image = null, ?UploadedFile $introVideo = null): array
    {
        return DB::transaction(function () use ($validated, $image, $introVideo) {
            if ($image) {
                $validated['image'] = $image
                    ->orient()
                    ->cover(800, 800)
                    ->optimize()
                    ->store(path: 'teachers', disk: 'public');
            }

            if ($introVideo) {
                $validated['intro_video'] = $introVideo->store('teacher_videos', 'public');
            }

            $randomPassword = '12345678';

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'] ?? null,
                'department' => 'Teacher',
                'password' => Hash::make($randomPassword),
                'image' => $validated['image'] ?? null,
                'suspend_status' => 0,
            ]);

            $role = Role::where('name', 'teacher')->firstOrFail();
            $user->assignRole($role);

            $teacher = Teacher::create([
                ...Arr::except($validated, ['name', 'email', 'mobile', 'image']),
                'user_id' => $user->id,
            ]);

            $teacher->setRelation('user', $user);

            return [
                'teacher' => $teacher,
                'user' => $user,
                'password' => $randomPassword,
            ];
        });
    }

    public function findForEdit(int $id): Teacher
    {
        return Teacher::query()->with('user')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Teacher $teacher, array $validated, ?Image $image = null, ?UploadedFile $introVideo = null): Teacher
    {
        return DB::transaction(function () use ($teacher, $validated, $image, $introVideo) {
            $linkedUser = $teacher->user;

            if ($image) {
                if ($linkedUser?->image && Storage::disk('public')->exists($linkedUser->image)) {
                    Storage::disk('public')->delete($linkedUser->image);
                }

                $validated['image'] = $image
                    ->orient()
                    ->cover(800, 800)
                    ->optimize()
                    ->store(path: 'teachers', disk: 'public');
            }

            if ($introVideo) {
                if ($teacher->intro_video && Storage::disk('public')->exists($teacher->intro_video)) {
                    Storage::disk('public')->delete($teacher->intro_video);
                }

                $validated['intro_video'] = $introVideo->store('teacher_videos', 'public');
            }

            $userPayload = Arr::only($validated, ['name', 'email', 'mobile', 'image', 'suspend_status']);
            $teacherPayload = Arr::except($validated, ['name', 'email', 'mobile', 'image', 'suspend_status']);

            $teacher->update($teacherPayload);

            if ($linkedUser) {
                $linkedUser->fill([
                    ...$userPayload,
                    'department' => 'Teacher',
                ]);
                $linkedUser->save();
                $teacher->setRelation('user', $linkedUser->fresh());
            }

            return $teacher;
        });
    }

    /**
     * @return array{teacher: Teacher, message: string}|null
     */
    public function toggleSuspend(int $id): ?array
    {
        return DB::transaction(function () use ($id) {
            $teacher = Teacher::with('user')->findOrFail($id);
            $user = $teacher->user;

            if (! $user) {
                return null;
            }

            $user->suspend_status = $user->suspend_status == 1 ? 0 : 1;
            $user->save();
            $teacher->setRelation('user', $user);

            return [
                'teacher' => $teacher,
                'message' => $teacher->suspend_status
                    ? 'Teacher suspended successfully.'
                    : 'Teacher reactivated successfully.',
            ];
        });
    }

    public function paginateForLanding(Request $request): LengthAwarePaginator
    {
        $perPage = Pagination::perPage($request);
        $search = $request->search;
        $isTop = $request->is_top;

        return Teacher::query()
            ->active()
            ->with('user:id,name,email,mobile,image,suspend_status')
            ->when($isTop !== null, function ($query) use ($isTop) {
                $query->where('is_top', (int) $isTop);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('bio', 'LIKE', "%{$search}%")
                        ->orWhere('expertise', 'LIKE', "%{$search}%")
                        ->orWhere('qualification', 'LIKE', "%{$search}%")
                        ->orWhere('about', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'LIKE', "%{$search}%"));
                });
            })
            ->select(
                'id',
                'user_id',
                'bio',
                'about',
                'specializations',
                'languages_spoken',
                'courses_can_teach',
                'interests',
                'expertise',
                'qualification',
                'years_of_exp',
                'intro_video',
                'country',
                'timezone',
                'is_top'
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findForPublicShow(int $id): ?Teacher
    {
        return Teacher::query()
            ->where('id', $id)
            ->active()
            ->select(
                'id',
                'user_id',
                'bio',
                'about',
                'specializations',
                'languages_spoken',
                'courses_can_teach',
                'interests',
                'expertise',
                'qualification',
                'years_of_exp',
                'intro_video',
                'country',
                'timezone',
                'is_top'
            )
            ->with([
                'user:id,name,email,mobile,image,suspend_status',
                'batches' => fn ($q) => $q->where('active_status', 1)
                    ->select(
                        'id',
                        'class_id',
                        'teacher_id',
                        'name',
                        'total_seat',
                        'filled_seat',
                        'start_date',
                        'end_date',
                        'status',
                        'active_status'
                    )
                    ->with([
                        'class:id,title,description,short_description,price,duration_in_days,total_classes,image,is_active',
                        'schedules:id,batch_id,day_of_week,start_time,end_time',
                    ])
                    ->latest(),
            ])
            ->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function formatPublicBatches(Teacher $teacher): Collection
    {
        $dayNames = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        return $teacher->batches->map(function (Batch $batch) use ($dayNames) {
            return [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_seat' => $batch->total_seat,
                'filled_seat' => $batch->filled_seat,
                'start_date' => optional($batch->start_date)->format('Y-m-d'),
                'end_date' => optional($batch->end_date)->format('Y-m-d'),
                'status' => $batch->status,
                'class' => $batch->class ? [
                    'id' => $batch->class->id,
                    'title' => $batch->class->title,
                    'description' => $batch->class->description,
                    'short_description' => $batch->class->short_description,
                    'price' => $batch->class->price,
                    'duration_in_days' => $batch->class->duration_in_days,
                    'total_classes' => $batch->class->total_classes,
                    'image' => $batch->class->image,
                    'image_url' => $batch->class->image_url,
                ] : null,
                'schedules' => $batch->schedules->map(fn ($schedule) => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'day' => $dayNames[$schedule->day_of_week] ?? 'Unknown',
                    'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
                ])->values(),
            ];
        })->values();
    }

    public function toggleTopStatus(int $id): Teacher
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->is_top = ! $teacher->is_top;
        $teacher->save();

        return $teacher;
    }

    public function findTimezoneForUser(User $user): ?Teacher
    {
        $user->loadMissing('teacher:id,user_id,country,timezone');

        return $user->teacher;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function normalizeMobile(array $validated): array
    {
        if (! empty($validated['mobile'])) {
            $validated['mobile'] = PhoneNormalizer::toE164($validated['mobile']);
        }

        return $validated;
    }
}
