<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VocabularyController extends Controller
{
    /** List vocabularies with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $query = Vocabulary::query();

        if ($request->search) {
            $query->where('word', 'like', '%'.$request->search.'%');
        }

        $vocabularies = $query->oldest()->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'data' => $vocabularies->items(),
            'pagination' => [
                'current_page' => $vocabularies->currentPage(),
                'per_page' => $vocabularies->perPage(),
                'total' => $vocabularies->total(),
                'last_page' => $vocabularies->lastPage(),
            ],
        ]);
    }

    /** Create a vocabulary entry. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255|unique:vocabularies,word',
            'meaning' => 'required|string',
            'example' => 'nullable|string',
            'pronunciation' => 'nullable|string',
            'part_of_speech' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')
                ->store('vocabulary', 'public');
        }

        $vocabulary = Vocabulary::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vocabulary created successfully',
            'data' => $vocabulary,
        ]);
    }

    /** Show a single vocabulary entry. */
    public function show(int $id): JsonResponse
    {
        $vocabulary = Vocabulary::find($id);

        if (! $vocabulary) {
            return response()->json([
                'success' => false,
                'message' => 'Vocabulary not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $vocabulary,
        ]);
    }

    /** Update a vocabulary entry. */
    public function update(Request $request, int $id): JsonResponse
    {
        $vocabulary = Vocabulary::findOrFail($id);

        $validated = $request->validate([
            'word' => 'required|string|max:255|unique:vocabularies,word,'.$vocabulary->id,
            'meaning' => 'required|string',
            'example' => 'nullable|string',
            'pronunciation' => 'nullable|string',
            'part_of_speech' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'status' => 'nullable|integer|in:1,0',
        ]);

        if ($request->hasFile('image')) {
            if ($vocabulary->image) {
                Storage::disk('public')->delete($vocabulary->image);
            }

            $validated['image'] = $request->file('image')
                ->store('vocabulary', 'public');
        }

        $vocabulary->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vocabulary updated successfully',
            'data' => $vocabulary,
        ]);
    }

    /** Delete a vocabulary entry. */
    public function destroy(int $id): JsonResponse
    {
        $vocabulary = Vocabulary::findOrFail($id);

        if ($vocabulary->image) {
            Storage::disk('public')->delete($vocabulary->image);
        }

        $vocabulary->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vocabulary deleted successfully',
        ]);
    }

    /** List active vocabularies for the frontend. */
    public function vocabularies(Request $request): JsonResponse
    {
        $perPage = Pagination::perPage($request);

        $vocabularies = Vocabulary::where('status', 1)
            ->oldest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vocabularies->items(),
            'pagination' => [
                'current_page' => $vocabularies->currentPage(),
                'per_page' => $vocabularies->perPage(),
                'total' => $vocabularies->total(),
                'last_page' => $vocabularies->lastPage(),
            ],
        ]);
    }
}
