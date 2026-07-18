<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BasicQuestion;

class BasicQuestionController extends Controller
{
    public function index(Request $request)
    {
        $query = BasicQuestion::query();

        if ($request->filled('search')) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        $basicQuestions = $query
            // ->latest()
            ->oldest()
            ->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $basicQuestions->items(),
            'pagination' => [
                'current_page' => $basicQuestions->currentPage(),
                'per_page'     => $basicQuestions->perPage(),
                'total'        => $basicQuestions->total(),
                'last_page'    => $basicQuestions->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'level'    => 'nullable|string|max:50',
        ]);

        $basicQuestion = BasicQuestion::create([
            'question' => $validated['question'],
            'level'    => $validated['level'] ?? null,
            'status'   => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Basic question created successfully.',
            'data' => $basicQuestion,
        ]);
    }

    public function show($id)
    {
        $basicQuestion = BasicQuestion::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $basicQuestion,
        ]);
    }

    public function update(Request $request, $id)
    {
        $basicQuestion = BasicQuestion::findOrFail($id);

        $validated = $request->validate([
            'question' => 'required|string',
            'level'    => 'nullable|string|max:50',
            'status' => 'nullable|in:0,1',
        ]);

        $basicQuestion->update([
            'question' => $validated['question'],
            'level'    => $validated['level'] ?? null,
            'status'   => $validated['status'] ?? $basicQuestion->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Basic question updated successfully.',
            'data' => $basicQuestion->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $basicQuestion = BasicQuestion::findOrFail($id);

        $basicQuestion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Basic question deleted successfully.',
        ]);
    }

    public function frontendList(Request $request)
    {
        $topics = BasicQuestion::where('status', 1)
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
