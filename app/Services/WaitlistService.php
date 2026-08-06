<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Waitlist;
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

    /**
     * @return array{items: array<int, Waitlist>, pagination: array<string, int>}
     */
    public function getForAdmin(Request $request): array
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

        $waitlists = $query->paginate(Pagination::perPage($request));

        return [
            'items' => $waitlists->items(),
            'pagination' => [
                'current_page' => $waitlists->currentPage(),
                'per_page' => $waitlists->perPage(),
                'total' => $waitlists->total(),
                'last_page' => $waitlists->lastPage(),
            ],
        ];
    }

    /**
     * @return array{items: array<int, Waitlist>, pagination: array<string, int>}
     */
    public function getForUser(int $userId, Request $request): array
    {
        $query = Waitlist::with([
            'batch:id,name,teacher_id',
            'batch.teacher:id,user_id',
            'batch.teacher.user:id,name',
        ])
            ->where('user_id', $userId)
            ->latest();

        $waitlists = $query->paginate(Pagination::perPage($request));

        return [
            'items' => $waitlists->items(),
            'pagination' => [
                'current_page' => $waitlists->currentPage(),
                'per_page' => $waitlists->perPage(),
                'total' => $waitlists->total(),
                'last_page' => $waitlists->lastPage(),
            ],
        ];
    }
}
