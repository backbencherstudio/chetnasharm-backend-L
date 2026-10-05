<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeakingTopic\StoreSpeakingTopicRequest;
use App\Http\Requests\SpeakingTopic\UpdateSpeakingTopicRequest;
use App\Http\Resources\SpeakingTopicResource;
use App\Services\SpeakingTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeakingTopicController extends Controller
{
    public function __construct(private readonly SpeakingTopicService $speakingTopics) {}

    /** List speaking topics with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->speakingTopics->index($request);

        return $this->paginated(
            SpeakingTopicResource::collection($result['items']),
            $result['pagination'],
            'Speaking topics retrieved successfully'
        );
    }

    /** Create a speaking topic. */
    public function store(StoreSpeakingTopicRequest $request): JsonResponse
    {
        $topic = $this->speakingTopics->store($request->validated());

        return $this->created(
            new SpeakingTopicResource($topic),
            'Speaking topic created successfully.'
        );
    }

    /** Show a single speaking topic. */
    public function show(int $id): JsonResponse
    {
        $topic = $this->speakingTopics->findOrFail($id);

        return $this->success(
            new SpeakingTopicResource($topic),
            'Speaking topic retrieved successfully'
        );
    }

    /** Update a speaking topic. */
    public function update(UpdateSpeakingTopicRequest $request, int $id): JsonResponse
    {
        $topic = $this->speakingTopics->findOrFail($id);
        $topic = $this->speakingTopics->update($topic, $request->validated());

        return $this->success(
            new SpeakingTopicResource($topic),
            'Speaking topic updated successfully.'
        );
    }

    /** Delete a speaking topic. */
    public function destroy(int $id): JsonResponse
    {
        $topic = $this->speakingTopics->findOrFail($id);
        $this->speakingTopics->destroy($topic);

        return $this->success(message: 'Speaking topic deleted successfully.');
    }

    /** List active speaking topics for the frontend. */
    public function frontendList(Request $request): JsonResponse
    {
        $result = $this->speakingTopics->frontendList($request);

        return $this->paginated(
            SpeakingTopicResource::collection($result['items']),
            $result['pagination'],
            'Speaking topics retrieved successfully'
        );
    }
}
