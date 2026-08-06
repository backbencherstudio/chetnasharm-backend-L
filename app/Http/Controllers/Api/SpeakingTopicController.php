<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeakingTopic\StoreSpeakingTopicRequest;
use App\Http\Requests\SpeakingTopic\UpdateSpeakingTopicRequest;
use App\Services\SpeakingTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeakingTopicController extends Controller
{
    public function __construct(private SpeakingTopicService $speakingTopics) {}

    /** List speaking topics with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->speakingTopics->index($request);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Create a speaking topic. */
    public function store(StoreSpeakingTopicRequest $request): JsonResponse
    {
        $topic = $this->speakingTopics->store($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic created successfully.',
            'data' => $topic,
        ]);
    }

    /** Show a single speaking topic. */
    public function show(int $id): JsonResponse
    {
        $topic = $this->speakingTopics->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $topic,
        ]);
    }

    /** Update a speaking topic. */
    public function update(UpdateSpeakingTopicRequest $request, int $id): JsonResponse
    {
        $topic = $this->speakingTopics->findOrFail($id);
        $topic = $this->speakingTopics->update($topic, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic updated successfully.',
            'data' => $topic,
        ]);
    }

    /** Delete a speaking topic. */
    public function destroy(int $id): JsonResponse
    {
        $topic = $this->speakingTopics->findOrFail($id);
        $this->speakingTopics->destroy($topic);

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic deleted successfully.',
        ]);
    }

    /** List active speaking topics for the frontend. */
    public function frontendList(Request $request): JsonResponse
    {
        $result = $this->speakingTopics->frontendList($request);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
