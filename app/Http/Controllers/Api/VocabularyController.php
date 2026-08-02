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
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
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

    /**
     * Store a newly created resource.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
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

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show($id)
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

    /**
     * Update the specified resource.
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
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

    /**
     * Remove the specified resource.
     *
     * @return JsonResponse
     */
    public function destroy($id)
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

    /**
     * List vocabularies for the frontend.
     *
     * @return JsonResponse
     */
    public function vocabularies(Request $request)
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
