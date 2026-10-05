<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vocabulary\StoreVocabularyRequest;
use App\Http\Requests\Vocabulary\UpdateVocabularyRequest;
use App\Http\Resources\VocabularyResource;
use App\Models\Vocabulary;
use App\Services\VocabularyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularyController extends Controller
{
    public function __construct(private readonly VocabularyService $vocabulary) {}

    /** List vocabularies with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->vocabulary->index($request);

        return $this->paginated(
            VocabularyResource::collection($result['items']),
            $result['pagination'],
            'Vocabularies retrieved successfully'
        );
    }

    /** Create a vocabulary entry. */
    public function store(StoreVocabularyRequest $request): JsonResponse
    {
        $vocabulary = $this->vocabulary->store($request->validated(), $request->file('image'));

        return $this->created(
            new VocabularyResource($vocabulary),
            'Vocabulary created successfully'
        );
    }

    /** Show a single vocabulary entry. */
    public function show(int $id): JsonResponse
    {
        $vocabulary = $this->vocabulary->find($id);

        if (! $vocabulary instanceof Vocabulary) {
            return $this->notFound('Vocabulary not found');
        }

        return $this->success(
            new VocabularyResource($vocabulary),
            'Vocabulary retrieved successfully'
        );
    }

    /** Update a vocabulary entry. */
    public function update(UpdateVocabularyRequest $request, int $id): JsonResponse
    {
        $vocabulary = $this->vocabulary->findOrFail($id);
        $vocabulary = $this->vocabulary->update($vocabulary, $request->validated(), $request->file('image'));

        return $this->success(
            new VocabularyResource($vocabulary),
            'Vocabulary updated successfully'
        );
    }

    /** Delete a vocabulary entry. */
    public function destroy(int $id): JsonResponse
    {
        $vocabulary = $this->vocabulary->findOrFail($id);
        $this->vocabulary->destroy($vocabulary);

        return $this->success(message: 'Vocabulary deleted successfully');
    }

    /** List active vocabularies for the frontend. */
    public function vocabularies(Request $request): JsonResponse
    {
        $result = $this->vocabulary->vocabularies($request);

        return $this->paginated(
            VocabularyResource::collection($result['items']),
            $result['pagination'],
            'Vocabularies retrieved successfully'
        );
    }
}
