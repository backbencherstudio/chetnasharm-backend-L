<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Waitlist\StoreWaitlistRequest;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function __construct(private WaitlistService $waitlist) {}

    /** Add the authenticated user to a batch waitlist. */
    public function store(StoreWaitlistRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->waitlist->store($user->id, (int) $request->validated('batch_id'));

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Added to waitlist successfully',
            'data' => $result['waitlist'],
        ]);
    }

    /** List waitlist entries for admin with optional batch filtering. */
    public function getForAdmin(Request $request): JsonResponse
    {
        $result = $this->waitlist->getForAdmin($request);

        return response()->json([
            'success' => true,
            'message' => 'Waitlist fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** List waitlist entries for the authenticated user. */
    public function getForUser(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->waitlist->getForUser($user->id, $request);

        return response()->json([
            'success' => true,
            'message' => 'Waitlist fetched successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
