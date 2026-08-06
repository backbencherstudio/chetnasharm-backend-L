<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Class\StoreClassRequest;
use App\Http\Requests\Class\UpdateClassRequest;
use App\Models\ClassModel;
use App\Services\ClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function __construct(private ClassService $classes) {}

    /** Display a paginated listing of classes. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->classes->index($request);

        return response()->json([
            'success' => true,
            'message' => 'Classes retrieved successfully',
            'data' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** Store a newly created class. */
    public function store(StoreClassRequest $request): JsonResponse
    {
        $class = $this->classes->store($request->validated(), $request->file('image'));

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully',
            'data' => $class,
        ], 201);
    }

    /** Get a class for editing. */
    public function edit(int $id): JsonResponse
    {
        $class = $this->classes->find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Class retrieved successfully',
            'data' => $class,
        ]);
    }

    /** Update the specified class. */
    public function update(UpdateClassRequest $request, int $id): JsonResponse
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        $class = $this->classes->update($class, $request->validated(), $request->file('image'));

        return response()->json([
            'success' => true,
            'message' => 'Class updated successfully',
            'data' => $class,
        ]);
    }

    /** Toggle the active status of a class. */
    public function status(int $id): JsonResponse
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        $class = $this->classes->toggleStatus($class);

        return response()->json([
            'success' => true,
            'message' => 'Class status updated successfully',
            'status' => $class->is_active,
        ]);
    }

    /** List classes for the public landing page. */
    public function landClass(Request $request): JsonResponse
    {
        $result = $this->classes->landClasses($request);

        return response()->json([
            'success' => true,
            'message' => 'Classes fetched successfully',

            'data' => $result['items'],

            'pagination' => $result['pagination'],
        ]);
    }

    /** List batches for a class on the landing page. */
    public function landBatch(Request $request, int $classId): JsonResponse
    {
        $result = $this->classes->landBatches($request, $classId);

        return response()->json([
            'success' => true,
            'message' => 'Batches fetched successfully',

            'data' => $result['items'],

            'pagination' => $result['pagination'],
        ]);
    }

    /** Get details for a single batch on the landing page. */
    public function singleBatch(int $batchId): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->classes->singleBatch($batchId, $user?->id);

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch fetched successfully',
            'data' => $result['batch'],
            'enrolled_status' => $result['enrolled'],
        ]);
    }

    /** List teachers linked to a class. */
    public function classTeachers(int $classId): JsonResponse
    {
        $teachers = $this->classes->classTeachers($classId);

        if ($teachers === null) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Class teachers retrieved successfully',
            'data' => $teachers,
        ]);
    }

    /** Get public details for a class. */
    public function singleClass(int $classId): JsonResponse
    {
        $class = $this->classes->singleClass($classId);

        if (! $class) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Class fetched successfully',
            'data' => $class,
        ]);
    }
}
