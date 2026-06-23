<?php

namespace Iquesters\Foundation\System\Http;

use Illuminate\Http\JsonResponse;
use Iquesters\Foundation\Constants\HttpStatusCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standardized API Response Handler
 * Provides consistent, industry-standard response structure across all API endpoints
 * 
 * Response Structure:
 * {
 *   "success": boolean,
 *   "status": int,
 *   "message": string,
 *   "response_schema": {
 *     "data": mixed,
 *     "errors": array|null,
 *     "meta": object|null,
 *     "links": object|null
 *   },
 *   "ui_context": {
 *     "component": string|null,
 *     "action": string|null,
 *     "redirect": string|null,
 *     "toast": object|null,
 *     "modal": object|null
 *   },
 *   "timestamp": string,
 *   "request_id": string|null
 * }
 */
class ApiResponse
{
    /**
     * Success response structure (2xx)
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @param array $meta
     * @param array $links
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function success(
        $data = null,
        string $message = 'Request successful',
        int $statusCode = HttpStatusCode::HTTP_OK,
        array $meta = [],
        array $links = [],
        array $uiContext = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'status' => $statusCode,
            'message' => $message,
            'response_schema' => [
                'data' => $data,
                'errors' => null,
                'meta' => !empty($meta) ? (object)$meta : null,
                'links' => !empty($links) ? (object)$links : null,
            ],
            'ui_context' => self::buildUIContext($uiContext),
        ];

        return response()->json($response, $statusCode);
    }

    /**
     * Error response structure (4xx, 5xx)
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @param array $meta
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function error(
        string $message = 'Request failed',
        int $statusCode = HttpStatusCode::HTTP_BAD_REQUEST,
        $errors = null,
        array $meta = [],
        array $uiContext = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'status' => $statusCode,
            'message' => $message,
            'response_schema' => [
                'data' => null,
                'errors' => self::normalizeErrors($errors),
                'meta' => !empty($meta) ? (object)$meta : null,
                'links' => null,
            ],
            'ui_context' => self::buildUIContext($uiContext),
        ];

        return response()->json($response, $statusCode);
    }

    /**
     * Redirect response structure (3xx)
     *
     * @param string $redirectUrl
     * @param string $message
     * @param int $statusCode
     * @param array $meta
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function redirect(
        string $redirectUrl,
        string $message = 'Redirecting',
        int $statusCode = HttpStatusCode::HTTP_FOUND,
        array $meta = [],
        array $uiContext = []
    ): JsonResponse {
        // Merge redirect URL into UI context
        $uiContext['redirect'] = $redirectUrl;
        
        $response = [
            'success' => true,
            'status' => $statusCode,
            'message' => $message,
            'response_schema' => [
                'data' => [
                    'redirect_url' => $redirectUrl,
                ],
                'errors' => null,
                'meta' => !empty($meta) ? (object)$meta : null,
                'links' => (object)[
                    'redirect' => $redirectUrl,
                ],
            ],
            'ui_context' => self::buildUIContext($uiContext),
        ];

        return response()->json($response, $statusCode)
            ->header('Location', $redirectUrl);
    }

    /**
     * Paginated response structure
     *
     * @param mixed $data
     * @param int $total
     * @param int $perPage
     * @param int $currentPage
     * @param string $message
     * @param array $links
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function paginated(
        $data,
        int $total,
        int $perPage,
        int $currentPage = 1,
        string $message = 'Request successful',
        array $links = [],
        array $uiContext = []
    ): JsonResponse {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : null;
        $to = $total > 0 ? min($currentPage * $perPage, $total) : null;
        $currentOffset = max(($currentPage - 1) * $perPage, 0);
        $lastOffset = $total > 0 ? max(($lastPage - 1) * $perPage, 0) : 0;
        $prevOffset = $currentPage > 1 ? max($currentOffset - $perPage, 0) : null;
        $nextOffset = $currentPage < $lastPage ? $currentOffset + $perPage : null;

        $meta = [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'has_more' => $currentPage < $lastPage,
            ]
        ];

        // Build pagination links
        $baseUrl = request()->url();
        $queryParams = request()->query();
        
        $paginationLinks = [
            'first' => self::buildPaginationUrl($baseUrl, $queryParams, 0, $perPage),
            'last' => self::buildPaginationUrl($baseUrl, $queryParams, $lastOffset, $perPage),
            'prev' => $prevOffset !== null
                ? self::buildPaginationUrl($baseUrl, $queryParams, $prevOffset, $perPage)
                : null,
            'next' => $nextOffset !== null
                ? self::buildPaginationUrl($baseUrl, $queryParams, $nextOffset, $perPage)
                : null,
        ];

        $links = array_merge($paginationLinks, $links);

        return self::success($data, $message, HttpStatusCode::HTTP_OK, $meta, $links, $uiContext);
    }

    /**
     * Created response (201)
     *
     * @param mixed $data
     * @param string $message
     * @param string|null $resourceUrl
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function created(
        $data = null, 
        string $message = 'Resource created successfully',
        ?string $resourceUrl = null,
        array $uiContext = []
    ): JsonResponse {
        $links = [];
        if ($resourceUrl) {
            $links['resource'] = $resourceUrl;
        }

        $response = self::success($data, $message, HttpStatusCode::HTTP_CREATED, [], $links, $uiContext);
        
        if ($resourceUrl) {
            $response->header('Location', $resourceUrl);
        }

        return $response;
    }

    /**
     * Accepted response (202) - For async operations
     *
     * @param mixed $data
     * @param string $message
     * @param string|null $statusUrl
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function accepted(
        $data = null,
        string $message = 'Request accepted for processing',
        ?string $statusUrl = null,
        array $uiContext = []
    ): JsonResponse {
        $links = [];
        if ($statusUrl) {
            $links['status'] = $statusUrl;
        }

        return self::success($data, $message, HttpStatusCode::HTTP_ACCEPTED, [], $links, $uiContext);
    }

    /**
     * No content response (204)
     *
     * @return Response
     */
    public static function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * Not modified response (304)
     *
     * @param array $meta
     * @return JsonResponse
     */
    public static function notModified(array $meta = []): JsonResponse
    {
        return self::success(null, 'Resource not modified', HttpStatusCode::HTTP_NOT_MODIFIED, $meta);
    }

    /**
     * Permanent redirect (301)
     *
     * @param string $url
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function movedPermanently(
        string $url,
        string $message = 'Resource moved permanently',
        array $uiContext = []
    ): JsonResponse {
        return self::redirect($url, $message, HttpStatusCode::HTTP_MOVED_PERMANENTLY, [], $uiContext);
    }

    /**
     * Temporary redirect (302)
     *
     * @param string $url
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function found(
        string $url,
        string $message = 'Resource found at new location',
        array $uiContext = []
    ): JsonResponse {
        return self::redirect($url, $message, HttpStatusCode::HTTP_FOUND, [], $uiContext);
    }

    /**
     * See other redirect (303)
     *
     * @param string $url
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function seeOther(
        string $url,
        string $message = 'See other resource',
        array $uiContext = []
    ): JsonResponse {
        return self::redirect($url, $message, HttpStatusCode::HTTP_SEE_OTHER, [], $uiContext);
    }

    /**
     * Temporary redirect (307) - Method preserved
     *
     * @param string $url
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function temporaryRedirect(
        string $url,
        string $message = 'Temporary redirect',
        array $uiContext = []
    ): JsonResponse {
        return self::redirect($url, $message, HttpStatusCode::HTTP_TEMPORARY_REDIRECT, [], $uiContext);
    }

    /**
     * Permanent redirect (308) - Method preserved
     *
     * @param string $url
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function permanentRedirect(
        string $url,
        string $message = 'Permanent redirect',
        array $uiContext = []
    ): JsonResponse {
        return self::redirect($url, $message, HttpStatusCode::HTTP_PERMANENTLY_REDIRECT, [], $uiContext);
    }

    /**
     * Bad request response (400)
     *
     * @param string $message
     * @param mixed $errors
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function badRequest(
        string $message = 'Bad request',
        $errors = null,
        array $uiContext = []
    ): JsonResponse {
        return self::error($message, HttpStatusCode::HTTP_BAD_REQUEST, $errors, [], $uiContext);
    }

    /**
     * Unauthorized response (401)
     *
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function unauthorized(
        string $message = 'Unauthorized',
        array $uiContext = []
    ): JsonResponse {
        return self::error($message, HttpStatusCode::HTTP_UNAUTHORIZED, null, [], $uiContext);
    }

    /**
     * Forbidden response (403)
     *
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function forbidden(
        string $message = 'Forbidden',
        array $uiContext = []
    ): JsonResponse {
        return self::error($message, HttpStatusCode::HTTP_FORBIDDEN, null, [], $uiContext);
    }

    /**
     * Not found response (404)
     *
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function notFound(
        string $message = 'Resource not found',
        array $uiContext = []
    ): JsonResponse {
        return self::error($message, HttpStatusCode::HTTP_NOT_FOUND, null, [], $uiContext);
    }

    /**
     * Method not allowed response (405)
     *
     * @param string $message
     * @param array $allowedMethods
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function methodNotAllowed(
        string $message = 'Method not allowed',
        array $allowedMethods = [],
        array $uiContext = []
    ): JsonResponse {
        $meta = !empty($allowedMethods) ? ['allowed_methods' => $allowedMethods] : [];
        
        $response = self::error($message, HttpStatusCode::HTTP_METHOD_NOT_ALLOWED, null, $meta, $uiContext);
        
        if (!empty($allowedMethods)) {
            $response->header('Allow', implode(', ', $allowedMethods));
        }

        return $response;
    }

    /**
     * Conflict response (409)
     *
     * @param string $message
     * @param mixed $errors
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function conflict(
        string $message = 'Conflict',
        $errors = null,
        array $uiContext = []
    ): JsonResponse {
        return self::error($message, HttpStatusCode::HTTP_CONFLICT, $errors, [], $uiContext);
    }

    /**
     * Validation error response (422)
     *
     * @param array $errors
     * @param string $message
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
        array $uiContext = []
    ): JsonResponse {
        return self::error($message, HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY, $errors, [], $uiContext);
    }

    /**
     * Too many requests response (429)
     *
     * @param string $message
     * @param int|null $retryAfter Seconds to wait before retry
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function tooManyRequests(
        string $message = 'Too many requests',
        ?int $retryAfter = null,
        array $uiContext = []
    ): JsonResponse {
        $meta = [];
        if ($retryAfter !== null) {
            $meta['retry_after'] = $retryAfter;
        }

        $response = self::error($message, HttpStatusCode::HTTP_TOO_MANY_REQUESTS, null, $meta, $uiContext);
        
        if ($retryAfter !== null) {
            $response->header('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * Server error response (500)
     *
     * @param string $message
     * @param mixed $errors
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function serverError(
        string $message = 'Internal server error',
        $errors = null,
        array $uiContext = []
    ): JsonResponse {
        return self::error($message, HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR, $errors, [], $uiContext);
    }

    /**
     * Service unavailable response (503)
     *
     * @param string $message
     * @param int|null $retryAfter
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function serviceUnavailable(
        string $message = 'Service unavailable',
        ?int $retryAfter = null,
        array $uiContext = []
    ): JsonResponse {
        $meta = [];
        if ($retryAfter !== null) {
            $meta['retry_after'] = $retryAfter;
        }

        $response = self::error($message, HttpStatusCode::HTTP_SERVICE_UNAVAILABLE, null, $meta, $uiContext);
        
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
     * @param array $meta
     * @param array $uiContext
     * @return JsonResponse
     */
    public static function byStatusCode(
        int $statusCode,
        $data = null,
        string $message = null,
        array $meta = [],
        array $uiContext = []
    ): JsonResponse {
        $isSuccess = HttpStatusCode::isInformational($statusCode)
            || HttpStatusCode::isSuccess($statusCode)
            || HttpStatusCode::isRedirect($statusCode);

        if ($message === null) {
            $message = self::getDefaultMessageForStatusCode($statusCode);
        }

        return $isSuccess
            ? self::success($data, $message, $statusCode, $meta, [], $uiContext)
            : self::error($message, $statusCode, $data, $meta, $uiContext);
    }

    /**
     * Build UI context structure
     *
     * @param array $context
     * @return array
     */
    private static function buildUIContext(array $context = []): array
    {
        return [
            'component' => $context['component'] ?? null,
            'action' => $context['action'] ?? null,
            'redirect' => $context['redirect'] ?? null,
            'toast' => isset($context['toast']) ? (object)$context['toast'] : null,
            'modal' => isset($context['modal']) ? (object)$context['modal'] : null,
            'refresh' => $context['refresh'] ?? false,
            'close' => $context['close'] ?? false,
            'custom' => isset($context['custom']) ? (object)$context['custom'] : null,
        ];
    }

    /**
     * Normalize errors to consistent format
     *
     * @param mixed $errors
     * @return array|null
     */
    private static function normalizeErrors($errors): ?array
    {
        if ($errors === null) {
            return null;
        }

        if (is_string($errors)) {
            return [
                'message' => $errors,
            ];
        }

        if (is_array($errors)) {
            return $errors;
        }

        if (is_object($errors) && method_exists($errors, 'toArray')) {
            return $errors->toArray();
        }

        return [
            'message' => (string)$errors,
        ];
    }

    /**
     * Build pagination URL
     *
     * @param string $baseUrl
     * @param array $queryParams
     * @param int $offset
     * @param int $length
     * @return string
     */
    private static function buildPaginationUrl(
        string $baseUrl,
        array $queryParams,
        int $offset,
        int $length
    ): string {
        unset($queryParams['page'], $queryParams['per_page']);

        $params = array_merge($queryParams, [
            'offset' => $offset,
            'length' => $length,
        ]);

        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Get request ID from headers or generate new one
     *
     * @return string|null
     */
    private static function getRequestId(): ?string
    {
        return request()->header('X-Request-ID') ?? request()->header('X-Request-Id');
    }

    /**
     * Get default message for status code
     *
     * @param int $statusCode
     * @return string
     */
    private static function getDefaultMessageForStatusCode(int $statusCode): string
    {
        return HttpStatusCode::defaultMessage($statusCode);
    }
}
