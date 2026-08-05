<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\BasicQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasicQuestionController extends Controller
{
    /** List basic questions with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $query = BasicQuestion::query();

        if ($request->filled('search')) {
            $query->where('question', 'like', '%'.$request->search.'%');
        }

        $basicQuestions = $query

            ->oldest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'data' => $basicQuestions->items(),
            'pagination' => [
                'current_page' => $basicQuestions->currentPage(),
                'per_page' => $basicQuestions->perPage(),
                'total' => $basicQuestions->total(),
                'last_page' => $basicQuestions->lastPage(),
            ],
        ]);
    }

    /** Create a basic question. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'level' => 'nullable|string|max:50',
        ]);

        $basicQuestion = BasicQuestion::create([
            'question' => $validated['question'],
            'level' => $validated['level'] ?? null,
            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Basic question created successfully.',
            'data' => $basicQuestion,
        ]);
    }

    /** Show a single basic question. */
    public function show(int $id): JsonResponse
    {
        $basicQuestion = BasicQuestion::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $basicQuestion,
        ]);
    }

    /** Update a basic question. */
    public function update(Request $request, int $id): JsonResponse
    {
        $basicQuestion = BasicQuestion::findOrFail($id);

        $validated = $request->validate([
            'question' => 'required|string',
            'level' => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1',
        ]);

        $basicQuestion->update([
            'question' => $validated['question'],
            'level' => $validated['level'] ?? null,
            'status' => $validated['status'] ?? $basicQuestion->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Basic question updated successfully.',
            'data' => $basicQuestion->fresh(),
        ]);
    }

    /** Delete a basic question. */
    public function destroy(int $id): JsonResponse
    {
        $basicQuestion = BasicQuestion::findOrFail($id);

        $basicQuestion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Basic question deleted successfully.',
        ]);
    }

    /** List active basic questions for the frontend. */
    public function frontendList(Request $request): JsonResponse
    {
        $topics = BasicQuestion::where('status', 1)
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
