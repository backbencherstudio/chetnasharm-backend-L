<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\BasicQuestion;
use Illuminate\Http\Request;

class BasicQuestionService
{
    /**
     * @return array{items: array<int, BasicQuestion>, pagination: array<string, int>}
     */
    public function index(Request $request): array
    {
        $query = BasicQuestion::query();

        if ($request->filled('search')) {
            $query->where('question', 'like', '%'.$request->search.'%');
        }

        $basicQuestions = $query
            ->oldest()
            ->paginate(Pagination::perPage($request));

        return [
            'items' => $basicQuestions->items(),
            'pagination' => [
                'current_page' => $basicQuestions->currentPage(),
                'per_page' => $basicQuestions->perPage(),
                'total' => $basicQuestions->total(),
                'last_page' => $basicQuestions->lastPage(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated): BasicQuestion
    {
        return BasicQuestion::create([
            'question' => $validated['question'],
            'level' => $validated['level'] ?? null,
            'status' => 1,
        ]);
    }

    public function findOrFail(int $id): BasicQuestion
    {
        return BasicQuestion::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(BasicQuestion $basicQuestion, array $validated): BasicQuestion
    {
        $basicQuestion->update([
            'question' => $validated['question'],
            'level' => $validated['level'] ?? null,
            'status' => $validated['status'] ?? $basicQuestion->status,
        ]);

        return $basicQuestion->fresh();
    }

    public function destroy(BasicQuestion $basicQuestion): void
    {
        $basicQuestion->delete();
    }

    /**
     * @return array{items: array<int, BasicQuestion>, pagination: array<string, int>}
     */
    public function frontendList(Request $request): array
    {
        $topics = BasicQuestion::where('status', 1)
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
