<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\SpeakingTopic;
use Illuminate\Http\Request;

class SpeakingTopicService
{
    /**
     * @return array{items: array<int, SpeakingTopic>, pagination: array<string, int>}
     */
    public function index(Request $request): array
    {
        $query = SpeakingTopic::query();

        if ($request->filled('search')) {
            $query->where('topic', 'like', '%'.$request->search.'%');
        }

        $speakingTopics = $query
            ->oldest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => $speakingTopics->items(),
            'pagination' => [
                'current_page' => $speakingTopics->currentPage(),
                'per_page' => $speakingTopics->perPage(),
                'total' => $speakingTopics->total(),
                'last_page' => $speakingTopics->lastPage(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated): SpeakingTopic
    {
        return SpeakingTopic::create([
            'topic' => $validated['topic'],
            'level' => $validated['level'] ?? null,
            'status' => 1,
        ]);
    }

    public function findOrFail(int $id): SpeakingTopic
    {
        return SpeakingTopic::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(SpeakingTopic $topic, array $validated): SpeakingTopic
    {
        $topic->update([
            'topic' => $validated['topic'],
            'level' => $validated['level'] ?? null,
            'status' => $validated['status'] ?? $topic->status,
        ]);

        return $topic->fresh();
    }

    public function destroy(SpeakingTopic $topic): void
    {
        $topic->delete();
    }

    /**
     * @return array{items: array<int, SpeakingTopic>, pagination: array<string, int>}
     */
    public function frontendList(Request $request): array
    {
        $topics = SpeakingTopic::where('status', 1)
            ->oldest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => $topics->items(),
            'pagination' => [
                'current_page' => $topics->currentPage(),
                'per_page' => $topics->perPage(),
                'total' => $topics->total(),
                'last_page' => $topics->lastPage(),
            ],
        ];
    }
}
