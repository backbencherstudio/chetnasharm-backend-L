<?php

namespace App\Services;

use App\Common\Pagination;
use App\Common\PhoneNormalizer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, ?UploadedFile $image = null): User
    {
        return DB::transaction(function () use ($validated, $image) {
            if ($image) {
                $validated['image'] = $image->store('users', 'public');
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $validated['mobile'] ?? null,
                'department' => $validated['department'] ?? null,
                'image' => $validated['image'] ?? null,
                'password' => Hash::make($validated['password']),
            ]);

            $role = Role::where('name', 'admin')->firstOrFail();
            $user->assignRole($role);

            return $user;
        });
    }

    public function findForEdit(int $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, array $validated, ?UploadedFile $image = null): User
    {
        return DB::transaction(function () use ($user, $validated, $image) {
            if ($image) {
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }

                $user->image = $image->store('users', 'public');
            }

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->mobile = $validated['mobile'] ?? $user->mobile;
            $user->department = $validated['department'] ?? $user->department;

            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            return $user;
        });
    }

    /**
     * @return array{
     *     users: LengthAwarePaginator,
     *     counts: array{total_users: int, admin: int, teacher: int, student: int}
     * }
     */
    public function paginateForAdmin(Request $request): array
    {
        $perPage = Pagination::perPage($request);
        $search = $request->query('search');
        $role = $request->query('role');

        $query = User::query()
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'admin');
            });

        if ($role && $role !== 'admin') {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        } else {
            $query->whereHas('roles');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $query->with(['roles:id,name'])
            ->select('id', 'name', 'email', 'mobile', 'department', 'image', 'suspend_status', 'provider');

        $users = $query->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'department' => $user->department,
                'image' => $user->image,
                'image_url' => $user->image_url,
                'suspended' => $user->suspend_status,
                'role' => $user->roles->pluck('name')->map(fn ($r) => ucfirst($r))->implode(', '),
            ];
        });

        $roleCounts = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->selectRaw('roles.name as role_name, COUNT(DISTINCT model_has_roles.model_id) as total')
            ->groupBy('roles.name')
            ->pluck('total', 'role_name');

        $totalUsers = (int) DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->distinct()
            ->count('model_id');

        return [
            'users' => $users,
            'counts' => [
                'total_users' => $totalUsers,
                'admin' => (int) ($roleCounts['admin'] ?? 0),
                'teacher' => (int) ($roleCounts['teacher'] ?? 0),
                'student' => (int) ($roleCounts['student'] ?? 0),
            ],
        ];
    }

    /**
     * @return array{user: User, message: string}
     */
    public function toggleSuspend(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $user->suspend_status = $user->suspend_status == 1 ? 0 : 1;
            $user->save();

            return [
                'user' => $user,
                'message' => $user->suspend_status ? 'User suspended successfully.' : 'User reactivated successfully.',
            ];
        });
    }

    public function updatePassword(User $user, string $newPassword, ?string $currentPassword = null): ?string
    {
        if (! $user->provider) {
            if (! Hash::check($currentPassword ?? '', $user->password)) {
                return 'Current password is incorrect.';
            }
        }

        $user->update([
            'password' => $newPassword,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProfile(User $user, array $validated, ?Image $image = null): User
    {
        unset($validated['image']);

        if ($image) {
            if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }

            $validated['image'] = $image
                ->orient()
                ->scale(width: 1200)
                ->optimize()
                ->store(path: 'users', disk: 'public');
        }

        $user->update($validated);

        return $user->refresh();
    }

    public function updateWhatsappMobile(int $userId, string $mobile): string
    {
        $user = User::findOrFail($userId);
        $normalizedMobile = PhoneNormalizer::toE164($mobile);

        $user->update([
            'mobile' => $normalizedMobile,
        ]);

        return $normalizedMobile;
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            if (
                $user->image &&
                ! filter_var($user->image, FILTER_VALIDATE_URL) &&
                Storage::disk('public')->exists($user->image)
            ) {
                Storage::disk('public')->delete($user->image);
            }

            $user->syncRoles([]);
            $user->delete();
        });
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
