<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Class\StoreClassRequest;
use App\Http\Requests\Class\UpdateClassRequest;
use App\Http\Resources\BatchResource;
use App\Http\Resources\ClassResource;
use App\Models\ClassModel;
use App\Services\ClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ClassController extends Controller
{
    public function __construct(private readonly ClassService $classes) {}

    /** Display a paginated listing of classes. */
    public function index(Request $request): JsonResponse
    {
        $result = $this->classes->index($request);

        return $this->paginated(
            ClassResource::collection($result['items']),
            $result['pagination'],
            'Classes retrieved successfully'
        );
    }

    /** Store a newly created class. */
    public function store(StoreClassRequest $request): JsonResponse
    {
        $class = $this->classes->store($request->validated(), $request->file('image'));

        return $this->created(new ClassResource($class), 'Class created successfully');
    }

    /** Get a class for editing. */
    public function edit(int $id): JsonResponse
    {
        $class = $this->classes->find($id);

        if (! $class instanceof ClassModel) {
            return $this->notFound('Class not found');
        }

        return $this->success(new ClassResource($class), 'Class retrieved successfully');
    }

    /** Update the specified class. */
    public function update(UpdateClassRequest $request, int $id): JsonResponse
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return $this->notFound('Class not found');
        }

        $class = $this->classes->update($class, $request->validated(), $request->file('image'));

        return $this->success(new ClassResource($class), 'Class updated successfully');
    }

    /** Toggle the active status of a class. */
    public function status(int $id): JsonResponse
    {
        $class = ClassModel::find($id);

        if (! $class) {
            return $this->notFound('Class not found');
        }

        $class = $this->classes->toggleStatus($class);

        return $this->success(
            message: 'Class status updated successfully',
            extra: ['status' => $class->is_active]
        );
    }

    /** List classes for the public landing page. */
    public function landClass(Request $request): JsonResponse
    {
        $result = $this->classes->landClasses($request);

        return $this->paginated(
            ClassResource::collection($result['items']),
            $result['pagination'],
            'Classes fetched successfully'
        );
    }

    /** List batches for a class on the landing page. */
    public function landBatch(Request $request, int $classId): JsonResponse
    {
        $result = $this->classes->landBatches($request, $classId);

        return $this->paginated(
            BatchResource::collection($result['items']),
            $result['pagination'],
            'Batches fetched successfully'
        );
    }

    /** Get details for a single batch on the landing page. */
    public function singleBatch(int $batchId): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->classes->singleBatch($batchId, $user?->id);

        if (! $result) {
            return $this->notFound('Batch not found');
        }

        return $this->success(
            data: new BatchResource($result['batch']),
            message: 'Batch fetched successfully',
            extra: ['enrolled_status' => $result['enrolled']]
        );
    }

    /** List teachers linked to a class. */
    public function classTeachers(int $classId): JsonResponse
    {
        $teachers = $this->classes->classTeachers($classId);

        if (! $teachers instanceof Collection) {
            return $this->notFound('Class not found');
        }

        return $this->success($teachers, 'Class teachers retrieved successfully');
    }

    /** Get public details for a class. */
    public function singleClass(int $classId): JsonResponse
    {
        $class = $this->classes->singleClass($classId);

        if (! $class instanceof ClassModel) {
            return $this->notFound('Class not found');
        }

        return $this->success(new ClassResource($class), 'Class fetched successfully');
    }
}
