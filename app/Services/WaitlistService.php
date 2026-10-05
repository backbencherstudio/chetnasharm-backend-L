<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Waitlist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class WaitlistService
{
    /**
     * @return array{waitlist: Waitlist}|array{error: string}
     */
    public function store(int $userId, int $batchId): array
    {
        $exists = Waitlist::where('user_id', $userId)
            ->where('batch_id', $batchId)
            ->exists();

        if ($exists) {
            return ['error' => 'Already in waitlist'];
        }

        $waitlist = Waitlist::create([
            'user_id' => $userId,
            'batch_id' => $batchId,
        ]);

        return ['waitlist' => $waitlist];
    }

    public function getForAdmin(Request $request): LengthAwarePaginator
    {
        $query = Waitlist::with([
            'user:id,name,email',
            'batch:id,name,teacher_id',
            'batch.teacher:id,user_id',
            'batch.teacher.user:id,name',
        ])->latest();

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        return $query->paginate(Pagination::perPage($request));
    }

    public function getForUser(int $userId, Request $request): LengthAwarePaginator
    {
        $query = Waitlist::with([
            'batch:id,name,teacher_id',
            'batch.teacher:id,user_id',
            'batch.teacher.user:id,name',
        ])
            ->where('user_id', $userId)
            ->latest();

        return $query->paginate(Pagination::perPage($request));
    }
}
