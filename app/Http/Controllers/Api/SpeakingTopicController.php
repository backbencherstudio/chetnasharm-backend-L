<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SpeakingTopic;

class SpeakingTopicController extends Controller
{
    public function index(Request $request)
    {
        $query = SpeakingTopic::query();

        if ($request->filled('search')) {
            $query->where('topic', 'like', '%' . $request->search . '%');
        }

        $speakingTopics = $query
            // ->latest()
            ->oldest()
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $speakingTopics->items(),
            'pagination' => [
                'current_page' => $speakingTopics->currentPage(),
                'per_page'     => $speakingTopics->perPage(),
                'total'        => $speakingTopics->total(),
                'last_page'    => $speakingTopics->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'topic'  => 'required|string',
            'level'  => 'nullable|string|max:50',
        ]);

        $topic = SpeakingTopic::create([
            'topic'  => $validated['topic'],
            'level'  => $validated['level'] ?? null,
            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic created successfully.',
            'data' => $topic,
        ]);
    }

    public function show($id)
    {
        $topic = SpeakingTopic::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $topic,
        ]);
    }

    public function update(Request $request, $id)
    {
        $topic = SpeakingTopic::findOrFail($id);

        $validated = $request->validate([
            'topic'  => 'required|string',
            'level'  => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1',
        ]);

        $topic->update([
            'topic'  => $validated['topic'],
            'level'  => $validated['level'] ?? null,
            'status' => $validated['status'] ?? $topic->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic updated successfully.',
            'data' => $topic->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $topic = SpeakingTopic::findOrFail($id);

        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Speaking topic deleted successfully.',
        ]);
    }

    public function frontendList(Request $request)
    {
        $topics = SpeakingTopic::where('status', 1)
            ->oldest()
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $topics->items(),
            'pagination' => [
                'current_page' => $topics->currentPage(),
                'per_page'     => $topics->perPage(),
                'total'        => $topics->total(),
                'last_page'    => $topics->lastPage(),
            ],
        ]);
    }

}
