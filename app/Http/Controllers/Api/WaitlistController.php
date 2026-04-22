<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Waitlist;
use App\Models\Batch;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
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
                'message' => 'Already in waitlist'
            ], 400);
        }

        $waitlist = Waitlist::create([
            'user_id' => $user->id,
            'batch_id' => $request->batch_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to waitlist successfully',
            'data' => $waitlist
        ]);
    }

    public function getForAdmin(Request $request)
    {
        $query = Waitlist::with([
            'user:id,name,email',
            'batch:id,name,teacher_id',
            'batch.teacher:id,name'
        ])->latest();

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        $waitlists = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Waitlist fetched successfully',
            'data' => $waitlists->items(),
            'pagination' => [
                'current_page' => $waitlists->currentPage(),
                'per_page'     => $waitlists->perPage(),
                'total'        => $waitlists->total(),
                'last_page'    => $waitlists->lastPage(),
            ]
        ]);
    }

    // public function destroy($batchId)
    // {
    //     $user = auth('api')->user();

    //     $waitlist = Waitlist::where('user_id', $user->id)
    //         ->where('batch_id', $batchId)
    //         ->first();

    //     if (!$waitlist) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Not found in waitlist'
    //         ], 404);
    //     }

    //     $waitlist->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Removed from waitlist'
    //     ]);
    // }

    public function getForUser(Request $request)
    {
        $user = auth('api')->user();

        $query = Waitlist::with([
            'batch:id,name,teacher_id',
            'batch.teacher:id,name'
        ])
        ->where('user_id', $user->id)
        ->latest();

        $waitlists = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Waitlist fetched successfully',
            'data' => $waitlists->items(),
            'pagination' => [
                'current_page' => $waitlists->currentPage(),
                'per_page'     => $waitlists->perPage(),
                'total'        => $waitlists->total(),
                'last_page'    => $waitlists->lastPage(),
            ]
        ]);
    }

}
