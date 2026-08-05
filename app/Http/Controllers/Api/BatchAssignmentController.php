<?php

namespace App\Http\Controllers\Api;

use App\Common\Pagination;
use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\BatchAssignment;
use App\Models\Enrollment;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BatchAssignmentController extends Controller
{
    private const FILE_RULES = 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';

    /**
     * List assignments for a batch (teacher).
     */
    public function index(Request $request, int $batchId): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $batch = $this->teacherBatch($teacher->id, $batchId);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        $assignments = BatchAssignment::query()
            ->where('batch_id', $batch->id)
            ->withCount('submissions')
            ->latest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Assignments fetched successfully',
            'data' => collect($assignments->items())->map(fn (BatchAssignment $assignment) => $this->formatAssignment($assignment))->values(),
            'pagination' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * Create an assignment on a batch.
     */
    public function store(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|'.self::FILE_RULES,
            'starts_at' => 'nullable|date',
            'due_at' => 'nullable|date|after_or_equal:starts_at',
            'total_marks' => 'required|numeric|min:1',
        ]);

        $batch = $this->teacherBatch($teacher->id, (int) $validated['batch_id']);

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid batch access',
            ], 403);
        }

        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('assignments', 'public');
        }

        $assignment = BatchAssignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'attachment' => $attachmentPath,
            'starts_at' => $validated['starts_at'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'total_marks' => $validated['total_marks'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assignment created successfully',
            'data' => $this->formatAssignment($assignment),
        ], 201);
    }

    /**
     * Show a single assignment (teacher).
     */
    public function show(int $id): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = BatchAssignment::query()
            ->withCount('submissions')
            ->where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment fetched successfully',
            'data' => $this->formatAssignment($assignment),
        ]);
    }

    /**
     * Update an assignment.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = BatchAssignment::query()
            ->where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|'.self::FILE_RULES,
            'starts_at' => 'nullable|date',
            'due_at' => 'nullable|date|after_or_equal:starts_at',
            'total_marks' => 'required|numeric|min:1',
        ]);

        $attachmentPath = $assignment->attachment;

        if ($request->hasFile('attachment')) {
            if ($assignment->attachment && Storage::disk('public')->exists($assignment->attachment)) {
                Storage::disk('public')->delete($assignment->attachment);
            }

            $attachmentPath = $request->file('attachment')->store('assignments', 'public');
        }

        $assignment->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'attachment' => $attachmentPath,
            'starts_at' => array_key_exists('starts_at', $validated) ? $validated['starts_at'] : $assignment->starts_at,
            'due_at' => array_key_exists('due_at', $validated) ? $validated['due_at'] : $assignment->due_at,
            'total_marks' => $validated['total_marks'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated successfully',
            'data' => $this->formatAssignment($assignment->fresh()->loadCount('submissions')),
        ]);
    }

    /**
     * Delete an assignment and related submission files.
     */
    public function destroy(int $id): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = BatchAssignment::query()
            ->with('submissions')
            ->where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        foreach ($assignment->submissions as $submission) {
            if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                Storage::disk('public')->delete($submission->file_path);
            }
        }

        if ($assignment->attachment && Storage::disk('public')->exists($assignment->attachment)) {
            Storage::disk('public')->delete($assignment->attachment);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment deleted successfully',
        ]);
    }

    /**
     * List submissions for an assignment (teacher).
     */
    public function submissions(Request $request, int $id): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $assignment = BatchAssignment::query()
            ->where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        $submissions = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->with('student:id,name,email')
            ->latest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Submissions fetched successfully',
            'data' => collect($submissions->items())->map(fn (AssignmentSubmission $submission) => [
                'id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'student_user_id' => $submission->student_user_id,
                'student_name' => $submission->student?->name,
                'student_email' => $submission->student?->email,
                'file_url' => $submission->file_path
                    ? asset('storage/'.$submission->file_path)
                    : null,
                'total_marks' => $assignment->total_marks,
                'obtained_marks' => $submission->obtained_marks,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at,
                'submitted_at' => $submission->updated_at,
                'created_at' => $submission->created_at,
            ])->values(),
            'pagination' => [
                'current_page' => $submissions->currentPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
                'last_page' => $submissions->lastPage(),
            ],
        ]);
    }

    /**
     * Student Assignment tab: active assignments across enrolled batches.
     */
    public function activeForStudent(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $batchIds = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Active assignments fetched successfully',
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => Pagination::perPage($request),
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        $search = $request->query('search');

        $query = BatchAssignment::query()
            ->active()
            ->whereIn('batch_id', $batchIds)
            ->with([
                'batch:id,name,class_id',
                'batch.class:id,title',
                'submissions' => function ($submissionQuery) use ($user) {
                    $submissionQuery->where('student_user_id', $user->id);
                },
            ])
            ->latest('due_at')
            ->latest();

        if ($search) {
            $query->where(function ($assignmentQuery) use ($search) {
                $assignmentQuery->where('title', 'like', "%{$search}%")
                    ->orWhereHas('batch', function ($batchQuery) use ($search) {
                        $batchQuery->where('name', 'like', "%{$search}%")
                            ->orWhereHas('class', fn ($classQuery) => $classQuery->where('title', 'like', "%{$search}%"));
                    });
            });
        }

        if ($request->boolean('pending_only')) {
            $query->whereDoesntHave('submissions', function ($submissionQuery) use ($user) {
                $submissionQuery->where('student_user_id', $user->id);
            });
        }

        $assignments = $query->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Active assignments fetched successfully',
            'data' => collect($assignments->items())->map(function (BatchAssignment $assignment) {
                $submission = $assignment->submissions->first();

                return [
                    ...$this->formatAssignment($assignment),
                    'batch_name' => $assignment->batch?->name,
                    'class_title' => $assignment->batch?->class?->title,
                    'is_open' => true,
                    'has_submitted' => $submission !== null,
                    'my_submission' => $this->formatMySubmission($submission),
                ];
            })->values(),
            'pagination' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * List assignments for a batch (student).
     */
    public function forStudent(Request $request, int $batchId): JsonResponse
    {
        $user = auth('api')->user();

        if (! $this->studentEnrolledInBatch($user->id, $batchId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not enrolled in this batch',
            ], 403);
        }

        $assignments = BatchAssignment::query()
            ->started()
            ->where('batch_id', $batchId)
            ->with(['submissions' => function ($query) use ($user) {
                $query->where('student_user_id', $user->id);
            }])
            ->latest()
            ->paginate(Pagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Assignments fetched successfully',
            'data' => collect($assignments->items())->map(function (BatchAssignment $assignment) {
                $submission = $assignment->submissions->first();

                return [
                    ...$this->formatAssignment($assignment),
                    'is_open' => $assignment->isOpenForSubmission(),
                    'my_submission' => $this->formatMySubmission($submission),
                ];
            })->values(),
            'pagination' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * Submit or replace a student assignment file.
     */
    public function submit(Request $request, int $assignmentId): JsonResponse
    {
        $user = auth('api')->user();

        $assignment = BatchAssignment::query()->find($assignmentId);

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found',
            ], 404);
        }

        if (! $this->studentEnrolledInBatch($user->id, $assignment->batch_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not enrolled in this batch',
            ], 403);
        }

        if (! $assignment->isOpenForSubmission()) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment submission is closed',
            ], 422);
        }

        $request->validate([
            'file' => 'required|'.self::FILE_RULES,
        ]);

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_user_id', $user->id)
            ->first();

        $filePath = $request->file('file')->store('assignment-submissions', 'public');

        if ($submission) {
            if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                Storage::disk('public')->delete($submission->file_path);
            }

            $submission->update([
                'file_path' => $filePath,
                'obtained_marks' => null,
                'feedback' => null,
                'graded_at' => null,
            ]);
        } else {
            $submission = AssignmentSubmission::create([
                'assignment_id' => $assignment->id,
                'student_user_id' => $user->id,
                'file_path' => $filePath,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'data' => [
                'id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'student_user_id' => $submission->student_user_id,
                'file_url' => asset('storage/'.$submission->file_path),
                'total_marks' => $assignment->total_marks,
                'obtained_marks' => $submission->obtained_marks,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at,
                'submitted_at' => $submission->updated_at,
            ],
        ]);
    }

    /**
     * Grade a student submission (teacher).
     */
    public function grade(Request $request, int $submissionId): JsonResponse
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not a teacher',
            ], 403);
        }

        $submission = AssignmentSubmission::query()
            ->with('assignment')
            ->where('id', $submissionId)
            ->first();

        if (! $submission || ! $submission->assignment || $submission->assignment->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found',
            ], 404);
        }

        $validated = $request->validate([
            'obtained_marks' => 'required|numeric|min:0|max:'.$submission->assignment->total_marks,
            'feedback' => 'nullable|string',
        ]);

        $submission->update([
            'obtained_marks' => $validated['obtained_marks'],
            'feedback' => $validated['feedback'] ?? null,
            'graded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission graded successfully',
            'data' => [
                'id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'student_user_id' => $submission->student_user_id,
                'total_marks' => $submission->assignment->total_marks,
                'obtained_marks' => $submission->obtained_marks,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at,
            ],
        ]);
    }

    /** Format an assignment for API responses. */
    private function formatAssignment(BatchAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'batch_id' => $assignment->batch_id,
            'teacher_id' => $assignment->teacher_id,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'attachment_url' => $assignment->attachment
                ? asset('storage/'.$assignment->attachment)
                : null,
            'starts_at' => $assignment->starts_at,
            'due_at' => $assignment->due_at,
            'total_marks' => $assignment->total_marks,
            'submissions_count' => $assignment->submissions_count ?? null,
            'created_at' => $assignment->created_at,
            'updated_at' => $assignment->updated_at,
        ];
    }

    /** Format the authenticated student's submission for API responses. */
    private function formatMySubmission(?AssignmentSubmission $submission): ?array
    {
        if (! $submission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'file_url' => asset('storage/'.$submission->file_path),
            'obtained_marks' => $submission->obtained_marks,
            'feedback' => $submission->feedback,
            'graded_at' => $submission->graded_at,
            'submitted_at' => $submission->updated_at,
        ];
    }

    /** Get the authenticated teacher record. */
    private function currentTeacher(): ?Teacher
    {
        $user = auth('api')->user();

        if (! $user) {
            return null;
        }

        $user->loadMissing('teacher:id,user_id');

        return $user->teacher;
    }

    /** Find a batch owned by the given teacher. */
    private function teacherBatch(int $teacherId, int $batchId): ?Batch
    {
        return Batch::query()
            ->where('id', $batchId)
            ->where('teacher_id', $teacherId)
            ->first();
    }

    /** Check whether a student is actively enrolled in a batch. */
    private function studentEnrolledInBatch(int $studentUserId, int $batchId): bool
    {
        return Enrollment::query()
            ->where('user_id', $studentUserId)
            ->where('batch_id', $batchId)
            ->where('status', 'active')
            ->exists();
    }
}
