<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\SpeakingTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeakingTopicController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $query = SpeakingTopic::query();

        if ($request->filled('search')) {
            $query->where('topic', 'like', '%'.$request->search.'%');
        }

        $speakingTopics = $query

            ->oldest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'data' => $speakingTopics->items(),
            'pagination' => [
                'current_page' => $speakingTopics->currentPage(),
                'per_page' => $speakingTopics->perPage(),
                'total' => $speakingTopics->total(),
                'last_page' => $speakingTopics->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic' => 'required|string',
            'level' => 'nullable|string|max:50',
        ]);

        $topic = SpeakingTopic::create([
            'topic' => $validated['topic'],
            'level' => $validated['level'] ?? null,
            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic created successfully.',
            'data' => $topic,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show($id)
    {
        $topic = SpeakingTopic::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $topic,
        ]);
    }

    /**
     * Update the specified resource.
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $topic = SpeakingTopic::findOrFail($id);

        $validated = $request->validate([
            'topic' => 'required|string',
            'level' => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1',
        ]);

        $topic->update([
            'topic' => $validated['topic'],
            'level' => $validated['level'] ?? null,
            'status' => $validated['status'] ?? $topic->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic updated successfully.',
            'data' => $topic->fresh(),
        ]);
    }

    /**
     * Remove the specified resource.
     *
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $topic = SpeakingTopic::findOrFail($id);

        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic deleted successfully.',
        ]);
    }

    /**
     * List resources for the frontend.
     *
     * @return JsonResponse
     */
    public function frontendList(Request $request)
    {
        $topics = SpeakingTopic::where('status', 1)
            ->oldest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'data' => $topics->items(),
            'pagination' => [
                'current_page' => $topics->currentPage(),
                'per_page' => $topics->perPage(),
                'total' => $topics->total(),
                'last_page' => $topics->lastPage(),
            ],
        ]);
    }
}
