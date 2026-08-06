<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BasicQuestion\StoreBasicQuestionRequest;
use App\Http\Requests\BasicQuestion\UpdateBasicQuestionRequest;
use App\Services\BasicQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasicQuestionController extends Controller
{
    public function __construct(private BasicQuestionService $basicQuestions) {}

    /** List basic questions with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->basicQuestions->index($request);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Create a basic question. */
    public function store(StoreBasicQuestionRequest $request): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->store($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Basic question created successfully.',
            'data' => $basicQuestion,
        ]);
    }

    /** Show a single basic question. */
    public function show(int $id): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $basicQuestion,
        ]);
    }

    /** Update a basic question. */
    public function update(UpdateBasicQuestionRequest $request, int $id): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->findOrFail($id);
        $basicQuestion = $this->basicQuestions->update($basicQuestion, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Basic question updated successfully.',
            'data' => $basicQuestion,
        ]);
    }

    /** Delete a basic question. */
    public function destroy(int $id): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->findOrFail($id);
        $this->basicQuestions->destroy($basicQuestion);

        return response()->json([
            'success' => true,
            'message' => 'Basic question deleted successfully.',
        ]);
    }

    /** List active basic questions for the frontend. */
    public function frontendList(Request $request): JsonResponse
    {
        $result = $this->basicQuestions->frontendList($request);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}
