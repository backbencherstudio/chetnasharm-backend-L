<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Waitlist\StoreWaitlistRequest;
use App\Http\Resources\WaitlistResource;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function __construct(private readonly WaitlistService $waitlist) {}

    /** Add the authenticated user to a batch waitlist. */
    public function store(StoreWaitlistRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->waitlist->store($user->id, (int) $request->validated('batch_id'));

        if (isset($result['error'])) {
            return $this->error($result['error'], 400);
        }

        return $this->created(
            new WaitlistResource($result['waitlist']),
            'Added to waitlist successfully'
        );
    }

    /** List waitlist entries for admin with optional batch filtering. */
    public function getForAdmin(Request $request): JsonResponse
    {
        $waitlists = $this->waitlist->getForAdmin($request);

        return $this->paginate(
            $waitlists,
            WaitlistResource::collection($waitlists->items()),
            'Waitlist fetched successfully'
        );
    }

    /** List waitlist entries for the authenticated user. */
    public function getForUser(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $waitlists = $this->waitlist->getForUser($user->id, $request);

        return $this->paginate(
            $waitlists,
            WaitlistResource::collection($waitlists->items()),
            'Waitlist fetched successfully'
        );
    }
}
