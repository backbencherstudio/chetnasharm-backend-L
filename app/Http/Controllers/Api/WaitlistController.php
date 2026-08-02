<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Waitlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    /**
     * Store a newly created resource.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();

        $request->validate([
            'batch_id' => 'required|exists:batches,id',
        ]);

        $exists = Waitlist::where('user_id', $user->id)
            ->where('batch_id', $request->batch_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Already in waitlist',
            ], 400);
        }

        $waitlist = Waitlist::create([
            'user_id' => $user->id,
            'batch_id' => $request->batch_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to waitlist successfully',
            'data' => $waitlist,
        ]);
    }

    /**
     * List waitlist entries for admin.
     *
     * @return JsonResponse
     */
    public function getForAdmin(Request $request)
    {
        $query = Waitlist::with([
            'user:id,name,email',
            'batch:id,name,teacher_id',
            'batch.teacher:id,name',
        ])->latest();

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        $waitlists = $query->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Waitlist fetched successfully',
            'data' => $waitlists->items(),
            'pagination' => [
                'current_page' => $waitlists->currentPage(),
                'per_page' => $waitlists->perPage(),
                'total' => $waitlists->total(),
                'last_page' => $waitlists->lastPage(),
            ],
        ]);
    }

    /**
     * List waitlist entries for the authenticated user.
     *
     * @return JsonResponse
     */
    public function getForUser(Request $request)
    {
        $user = auth('api')->user();

        $query = Waitlist::with([
            'batch:id,name,teacher_id',
            'batch.teacher:id,name',
        ])
            ->where('user_id', $user->id)
            ->latest();

        $waitlists = $query->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Waitlist fetched successfully',
            'data' => $waitlists->items(),
            'pagination' => [
                'current_page' => $waitlists->currentPage(),
                'per_page' => $waitlists->perPage(),
                'total' => $waitlists->total(),
                'last_page' => $waitlists->lastPage(),
            ],
        ]);
    }
}
