<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vocabulary\StoreVocabularyRequest;
use App\Http\Requests\Vocabulary\UpdateVocabularyRequest;
use App\Services\VocabularyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularyController extends Controller
{
    public function __construct(private VocabularyService $vocabulary) {}

    /** List vocabularies with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->vocabulary->index($request);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Create a vocabulary entry. */
    public function store(StoreVocabularyRequest $request): JsonResponse
    {
        $vocabulary = $this->vocabulary->store($request->validated(), $request->file('image'));

        return response()->json([
            'success' => true,
            'message' => 'Vocabulary created successfully',
            'data' => $vocabulary,
        ]);
    }

    /** Show a single vocabulary entry. */
    public function show(int $id): JsonResponse
    {
        $vocabulary = $this->vocabulary->find($id);

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
    public function update(UpdateVocabularyRequest $request, int $id): JsonResponse
    {
        $vocabulary = $this->vocabulary->findOrFail($id);
        $vocabulary = $this->vocabulary->update($vocabulary, $request->validated(), $request->file('image'));

        return response()->json([
            'success' => true,
            'message' => 'Vocabulary updated successfully',
            'data' => $vocabulary,
        ]);
    }

    /** Delete a vocabulary entry. */
    public function destroy(int $id): JsonResponse
    {
        $vocabulary = $this->vocabulary->findOrFail($id);
        $this->vocabulary->destroy($vocabulary);

        return response()->json([
            'success' => true,
            'message' => 'Vocabulary deleted successfully',
        ]);
    }

    /** List active vocabularies for the frontend. */
    public function vocabularies(Request $request): JsonResponse
    {
        $result = $this->vocabulary->vocabularies($request);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
