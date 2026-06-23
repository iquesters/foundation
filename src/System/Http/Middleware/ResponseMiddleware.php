<?php

namespace Iquesters\Foundation\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Iquesters\Foundation\Constants\HttpStatusCode;
use Iquesters\Foundation\System\Traits\Loggable;
use Iquesters\Foundation\System\Http\ApiResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResponseMiddleware
{
    use Loggable;

    /**
     * Handle an outgoing response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->logMethodStart("==================================================");

        $requestId = $request->header('X-Request-ID')
            ?? 'req_' . now()->timestamp . '_' . Str::random(6);
        $request->headers->set('X-Request-ID', $requestId);
        
        $response = $next($request);
        
        $startTime = LARAVEL_START;

        $this->logDebug("Processing response...");

        // Process response based on type and status
        $response = $this->processResponse($request, $response);

        $this->logDebug("Processing response done.");
        $this->logDebug("--------------------------------------------------");

        $this->logMethodEnd("##################################################");

        return $response;
    }

    /**
     * Process the response based on type and status code
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    protected function processResponse(Request $request, Response $response): Response
    {
        // Only process JSON responses for API routes
        if (!$this->isApiRequest($request)) {
            $this->logDebug("Non-API request - skipping response processing");
            return $response;
        }

        // If response is already in our standard format, return it
        if ($this->isStandardizedResponse($response)) {
            $this->logDebug("Response already in standard format");
            return $response;
        }

        // Standardize the response
        $response = $this->standardizeResponse($response);

        return $this->attachInfraMetadata($request, $response);

    }
    
    protected function attachInfraMetadata(Request $request, Response $response): Response
    {
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $content = json_decode($response->getContent(), true);

        $content['version'] = config('api.version', 'v1');
        $content['request_id'] = $request->header('X-Request-ID');
        $content['timestamp'] = now()->toIso8601String();

        $content['trace'] = app()->environment('production') ? null : [
            'execution_time_ms' => round((microtime(true) - LARAVEL_START) * 1000),
            'cache_hit' => false,
        ];

        return response()->json(
            $content,
            $response->getStatusCode(),
            $response->headers->all()
        );
    }


    /**
     * Check if request is an API request
     *
     * @param Request $request
     * @return bool
     */
    protected function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Check if response is already in standardized format
     *
     * @param Response $response
     * @return bool
     */
    protected function isStandardizedResponse(Response $response): bool
    {
        if (!$response instanceof JsonResponse) {
            return false;
        }

        $content = json_decode($response->getContent(), true);
        
        return is_array($content) 
            && isset($content['success']) 
            && isset($content['status'])
            && isset($content['response_schema'])
            && isset($content['ui_context']);
    }

    /**
     * Standardize non-standard responses
     *
     * @param Response $response
     * @return Response
     */
    protected function standardizeResponse(Response $response): Response
    {
        $statusCode = $response->getStatusCode();

        // Handle different status code ranges
        switch (true) {
            case HttpStatusCode::isInformational($statusCode):
                return $this->handleInformationalResponse($response);

            case HttpStatusCode::isSuccess($statusCode):
                return $this->handleSuccessResponse($response);

            case HttpStatusCode::isRedirect($statusCode):
                return $this->handleRedirectResponse($response);

            case $statusCode === HttpStatusCode::HTTP_BAD_REQUEST:
                return $this->handleBadRequest($response);
            case $statusCode === HttpStatusCode::HTTP_UNAUTHORIZED:
                return $this->handleUnauthorized($response);
            case $statusCode === HttpStatusCode::HTTP_FORBIDDEN:
                return $this->handleForbidden($response);
            case $statusCode === HttpStatusCode::HTTP_NOT_FOUND:
                return $this->handleNotFound($response);
            case $statusCode === HttpStatusCode::HTTP_METHOD_NOT_ALLOWED:
                return $this->handleMethodNotAllowed($response);
            case $statusCode === HttpStatusCode::HTTP_CONFLICT:
                return $this->handleConflict($response);
            case $statusCode === HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY:
                return $this->handleValidationError($response);
            case $statusCode === HttpStatusCode::HTTP_TOO_MANY_REQUESTS:
                return $this->handleTooManyRequests($response);
            case HttpStatusCode::isClientError($statusCode):
                return $this->handleClientError($response);

            case HttpStatusCode::isServerError($statusCode):
                return $this->handleServerError($response);

            default:
                return $this->handleGenericError($response);
        }
    }

    /**
     * Handle informational responses (1xx)
     */
    protected function handleInformationalResponse(Response $response): Response
    {
        $this->logDebug("Processing informational response: " . $response->getStatusCode());

        $content = $this->getResponseContent($response);
        $statusCode = $response->getStatusCode();
        $message = $this->extractMessage($content, HttpStatusCode::defaultMessage($statusCode));
        $meta = $this->extractMeta($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::byStatusCode($statusCode, $this->extractData($content), $message, $meta, $uiContext);
    }

    /**
     * Handle success responses (2xx)
     */
    protected function handleSuccessResponse(Response $response): Response
    {
        $this->logDebug("Processing success response: " . $response->getStatusCode());
        
        $content = $this->getResponseContent($response);
        $statusCode = $response->getStatusCode();

        // Extract data
        $data = $this->extractData($content);
        $message = $this->extractMessage($content, 'Request successful');
        $meta = $this->extractMeta($content);
        $links = $this->extractLinks($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::success($data, $message, $statusCode, $meta, $links, $uiContext);
    }

    /**
     * Handle redirect responses (3xx)
     */
    protected function handleRedirectResponse(Response $response): Response
    {
        $this->logDebug("Processing redirect response: " . $response->getStatusCode());
        
        $statusCode = $response->getStatusCode();
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Redirecting');
        
        // Get redirect URL from Location header or content
        $redirectUrl = $response->headers->get('Location');
        if (!$redirectUrl && is_array($content)) {
            $redirectUrl = $content['redirect_url'] ?? $content['url'] ?? $content['location'] ?? null;
        }

        if (!$redirectUrl) {
            $this->logDebug("Redirect response without URL - treating as generic response");
            return $this->handleSuccessResponse($response);
        }

        $meta = $this->extractMeta($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::redirect($redirectUrl, $message, $statusCode, $meta, $uiContext);
    }

    /**
     * Handle bad request (400)
     */
    protected function handleBadRequest(Response $response): Response
    {
        $this->logDebug("Processing bad request response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Bad request');
        $errors = $this->extractErrors($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::badRequest($message, $errors, $uiContext);
    }

    /**
     * Handle unauthorized (401)
     */
    protected function handleUnauthorized(Response $response): Response
    {
        $this->logDebug("Processing unauthorized response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Unauthorized');
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::unauthorized($message, $uiContext);
    }

    /**
     * Handle forbidden (403)
     */
    protected function handleForbidden(Response $response): Response
    {
        $this->logDebug("Processing forbidden response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Forbidden');
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::forbidden($message, $uiContext);
    }

    /**
     * Handle not found (404)
     */
    protected function handleNotFound(Response $response): Response
    {
        $this->logDebug("Processing not found response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Resource not found');
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::notFound($message, $uiContext);
    }

    /**
     * Handle method not allowed (405)
     */
    protected function handleMethodNotAllowed(Response $response): Response
    {
        $this->logDebug("Processing method not allowed response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Method not allowed');
        $uiContext = $this->extractUIContext($content);
        
        $allowHeader = $response->headers->get('Allow');
        $allowedMethods = $allowHeader ? explode(', ', $allowHeader) : [];

        return ApiResponse::methodNotAllowed($message, $allowedMethods, $uiContext);
    }

    /**
     * Handle conflict (409)
     */
    protected function handleConflict(Response $response): Response
    {
        $this->logDebug("Processing conflict response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Conflict');
        $errors = $this->extractErrors($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::conflict($message, $errors, $uiContext);
    }

    /**
     * Handle validation error (422)
     */
    protected function handleValidationError(Response $response): Response
    {
        $this->logDebug("Processing validation error response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Validation failed');
        $errors = $this->extractErrors($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::validationError($errors ?: [], $message, $uiContext);
    }

    /**
     * Handle too many requests (429)
     */
    protected function handleTooManyRequests(Response $response): Response
    {
        $this->logDebug("Processing too many requests response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Too many requests');
        $retryAfter = $response->headers->get('Retry-After');
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::tooManyRequests($message, $retryAfter ? (int)$retryAfter : null, $uiContext);
    }

    /**
     * Handle generic client errors (4xx)
     */
    protected function handleClientError(Response $response): Response
    {
        $this->logDebug("Processing client error response: " . $response->getStatusCode());
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Client error');
        $errors = $this->extractErrors($content);
        $meta = $this->extractMeta($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::error($message, $response->getStatusCode(), $errors, $meta, $uiContext);
    }

    /**
     * Handle server errors (5xx)
     */
    protected function handleServerError(Response $response): Response
    {
        $this->logDebug("Processing server error response: " . $response->getStatusCode());
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Internal server error');
        
        // Hide error details in production
        $errors = app()->environment('production') 
            ? null 
            : $this->extractErrors($content);
            
        $uiContext = $this->extractUIContext($content);

        if ($response->getStatusCode() === HttpStatusCode::HTTP_SERVICE_UNAVAILABLE) {
            $retryAfter = $response->headers->get('Retry-After');
            return ApiResponse::serviceUnavailable($message, $retryAfter ? (int)$retryAfter : null, $uiContext);
        }

        return ApiResponse::serverError($message, $errors, $uiContext);
    }

    /**
     * Handle generic errors
     */
    protected function handleGenericError(Response $response): Response
    {
        $this->logDebug("Processing generic error response: " . $response->getStatusCode());
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Request failed');
        $errors = $this->extractErrors($content);
        $meta = $this->extractMeta($content);
        $uiContext = $this->extractUIContext($content);

        return ApiResponse::error($message, $response->getStatusCode(), $errors, $meta, $uiContext);
    }

    /**
     * Get response content as array
     */
    protected function getResponseContent(Response $response): mixed
    {
        if ($response instanceof JsonResponse) {
            return json_decode($response->getContent(), true) ?? [];
        }

        $content = $response->getContent();
        $decoded = json_decode($content, true);
        
        return $decoded ?? $content;
    }

    /**
     * Extract data from response content
     */
    protected function extractData(mixed $content): mixed
    {
        if (!is_array($content)) {
            return $content;
        }

        // Check for nested data structure
        if (isset($content['data'])) {
            return $content['data'];
        }

        // Check for response_schema.data structure
        if (isset($content['response_schema']['data'])) {
            return $content['response_schema']['data'];
        }

        // Return entire content as data
        return $content;
    }

    /**
     * Extract message from response content
     */
    protected function extractMessage(mixed $content, string $default): string
    {
        if (is_array($content)) {
            return $content['message'] ?? $content['error'] ?? $default;
        }

        if (is_string($content)) {
            return $content ?: $default;
        }

        return $default;
    }

    /**
     * Extract errors from response content
     */
    protected function extractErrors(mixed $content): mixed
    {
        if (!is_array($content)) {
            return null;
        }

        return $content['errors'] 
            ?? $content['error'] 
            ?? $content['response_schema']['errors'] 
            ?? null;
    }

    /**
     * Extract meta from response content
     */
    protected function extractMeta(mixed $content): array
    {
        if (!is_array($content)) {
            return [];
        }

        return $content['meta'] 
            ?? $content['response_schema']['meta'] 
            ?? [];
    }

    /**
     * Extract links from response content
     */
    protected function extractLinks(mixed $content): array
    {
        if (!is_array($content)) {
            return [];
        }

        return $content['links'] 
            ?? $content['response_schema']['links'] 
            ?? [];
    }

    /**
     * Extract UI context from response content
     */
    protected function extractUIContext(mixed $content): array
    {
        if (!is_array($content)) {
            return [];
        }

        return $content['ui_context'] 
            ?? $content['uiContext'] 
            ?? [];
    }
}
