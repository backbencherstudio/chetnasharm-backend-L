<?php

declare(strict_types=1);

namespace App\Common;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $extra
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = Response::HTTP_OK,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => true,
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $extra), $code);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function paginate(
        LengthAwarePaginator $paginator,
        mixed $resource = null,
        string $message = 'Data retrieved successfully',
        array $extra = [],
        int $code = Response::HTTP_OK
    ): JsonResponse {
        $data = $resource ?? $paginator->items();

        return response()->json(array_merge([
            'success' => true,
            'status' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $extra), $code);
    }

    /**
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>  $extra
     */
    protected function paginated(
        mixed $data,
        array $pagination,
        string $message = 'Data retrieved successfully',
        array $extra = [],
        int $code = Response::HTTP_OK
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => true,
            'status' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination,
        ], $extra), $code);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function created(
        mixed $data = null,
        string $message = 'Resource created successfully',
        array $extra = []
    ): JsonResponse {
        return $this->success($data, $message, Response::HTTP_CREATED, $extra);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $extra
     */
    protected function error(
        string $message = 'Error',
        int $code = Response::HTTP_BAD_REQUEST,
        array $errors = [],
        array $extra = []
    ): JsonResponse {
        $response = array_merge([
            'success' => false,
            'status' => false,
            'message' => $message,
        ], $extra);

        if ($errors !== []) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND);
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Return a raw array response (ensuring status and success flags if applicable).
     *
     * @param  array<string, mixed>  $data
     */
    protected function respond(array $data, int $code = Response::HTTP_OK): JsonResponse
    {
        return response()->json($data, $code);
    }
}
