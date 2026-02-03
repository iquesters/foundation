<?php

namespace Iquesters\Foundation\System\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standardized API Response Handler
 * Provides consistent response structure across all API endpoints
 */
class ApiResponse
{
    /**
     * Success response structure
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @param array $meta
     * @return JsonResponse
     */
    public static function success(
        $data = null,
        string $message = 'Request successful',
        int $statusCode = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'status' => $statusCode,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        $response['timestamp'] = now()->toIso8601String();

        return response()->json($response, $statusCode);
    }

    /**
     * Error response structure
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @param array $meta
     * @return JsonResponse
     */
    public static function error(
        string $message = 'Request failed',
        int $statusCode = Response::HTTP_BAD_REQUEST,
        $errors = null,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'status' => $statusCode,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        $response['timestamp'] = now()->toIso8601String();

        return response()->json($response, $statusCode);
    }

    /**
     * Paginated response structure
     *
     * @param mixed $data
     * @param int $total
     * @param int $perPage
     * @param int $currentPage
     * @param string $message
     * @return JsonResponse
     */
    public static function paginated(
        $data,
        int $total,
        int $perPage,
        int $currentPage = 1,
        string $message = 'Request successful'
    ): JsonResponse {
        $lastPage = (int) ceil($total / $perPage);

        return self::success($data, $message, Response::HTTP_OK, [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'from' => (($currentPage - 1) * $perPage) + 1,
                'to' => min($currentPage * $perPage, $total),
            ]
        ]);
    }

    /**
     * Created response (201)
     *
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    public static function created($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * No content response (204)
     *
     * @return JsonResponse
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Not found response (404)
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Unauthorized response (401)
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Forbidden response (403)
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Validation error response (422)
     *
     * @param array $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Server error response (500)
     *
     * @param string $message
     * @param mixed $errors
     * @return JsonResponse
     */
    public static function serverError(
        string $message = 'Internal server error',
        $errors = null
    ): JsonResponse {
        return self::error($message, Response::HTTP_INTERNAL_SERVER_ERROR, $errors);
    }

    /**
     * Service unavailable response (503)
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function serviceUnavailable(string $message = 'Service unavailable'): JsonResponse
    {
        return self::error($message, Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Too many requests response (429)
     *
     * @param string $message
     * @param int|null $retryAfter Seconds to wait before retry
     * @return JsonResponse
     */
    public static function tooManyRequests(
        string $message = 'Too many requests',
        ?int $retryAfter = null
    ): JsonResponse {
        $response = self::error($message, Response::HTTP_TOO_MANY_REQUESTS);
        
        if ($retryAfter !== null) {
            $response->header('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * Generic response by status code
     *
     * @param int $statusCode
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    public static function byStatusCode(
        int $statusCode,
        $data = null,
        string $message = null
    ): JsonResponse {
        $isSuccess = $statusCode >= 200 && $statusCode < 300;

        if ($message === null) {
            $message = self::getDefaultMessageForStatusCode($statusCode);
        }

        return $isSuccess
            ? self::success($data, $message, $statusCode)
            : self::error($message, $statusCode, $data);
    }

    /**
     * Get default message for status code
     *
     * @param int $statusCode
     * @return string
     */
    private static function getDefaultMessageForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'Request successful',
            201 => 'Resource created successfully',
            204 => 'No content',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Internal server error',
            503 => 'Service unavailable',
            default => 'Request processed',
        };
    }
}