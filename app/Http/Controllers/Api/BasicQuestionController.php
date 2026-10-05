<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BasicQuestion\StoreBasicQuestionRequest;
use App\Http\Requests\BasicQuestion\UpdateBasicQuestionRequest;
use App\Http\Resources\BasicQuestionResource;
use App\Services\BasicQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasicQuestionController extends Controller
{
    public function __construct(private readonly BasicQuestionService $basicQuestions) {}

    /** List basic questions with optional search filtering. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->basicQuestions->index($request);

        return $this->paginated(
            BasicQuestionResource::collection($result['items']),
            $result['pagination'],
            'Basic questions retrieved successfully'
        );
    }

    /** Create a basic question. */
    public function store(StoreBasicQuestionRequest $request): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->store($request->validated());

        return $this->created(
            new BasicQuestionResource($basicQuestion),
            'Basic question created successfully.'
        );
    }

    /** Show a single basic question. */
    public function show(int $id): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->findOrFail($id);

        return $this->success(
            new BasicQuestionResource($basicQuestion),
            'Basic question retrieved successfully'
        );
    }

    /** Update a basic question. */
    public function update(UpdateBasicQuestionRequest $request, int $id): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->findOrFail($id);
        $basicQuestion = $this->basicQuestions->update($basicQuestion, $request->validated());

        return $this->success(
            new BasicQuestionResource($basicQuestion),
            'Basic question updated successfully.'
        );
    }

    /** Delete a basic question. */
    public function destroy(int $id): JsonResponse
    {
        $basicQuestion = $this->basicQuestions->findOrFail($id);
        $this->basicQuestions->destroy($basicQuestion);

        return $this->success(message: 'Basic question deleted successfully.');
    }

    /** List active basic questions for the frontend. */
    public function frontendList(Request $request): JsonResponse
    {
        $result = $this->basicQuestions->frontendList($request);

        return $this->paginated(
            BasicQuestionResource::collection($result['items']),
            $result['pagination'],
            'Basic questions retrieved successfully'
        );
    }
}
