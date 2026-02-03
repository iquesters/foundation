<?php

namespace Iquesters\Foundation\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Iquesters\Foundation\System\Traits\Loggable;
use Iquesters\Foundation\System\Http\ApiResponse;

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

        $response = $next($request);

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
        return $this->standardizeResponse($response);
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
            && isset($content['timestamp']);
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

        // Handle different status codes
        switch (true) {
            case $statusCode >= 200 && $statusCode < 300:
                return $this->handleSuccessResponse($response);
                
            case $statusCode === 400:
                return $this->handleBadRequest($response);
                
            case $statusCode === 401:
                return $this->handleUnauthorized($response);
                
            case $statusCode === 403:
                return $this->handleForbidden($response);
                
            case $statusCode === 404:
                return $this->handleNotFound($response);
                
            case $statusCode === 422:
                return $this->handleValidationError($response);
                
            case $statusCode === 429:
                return $this->handleTooManyRequests($response);
                
            case $statusCode >= 500:
                return $this->handleServerError($response);
                
            default:
                return $this->handleGenericError($response);
        }
    }

    /**
     * Handle success responses (2xx)
     */
    protected function handleSuccessResponse(Response $response): Response
    {
        $this->logDebug("Processing success response: " . $response->getStatusCode());
        
        $content = $this->getResponseContent($response);
        $statusCode = $response->getStatusCode();

        // If content has 'data' key, use it; otherwise wrap the entire content
        $data = is_array($content) && isset($content['data']) 
            ? $content['data'] 
            : $content;

        $message = $this->extractMessage($content, 'Request successful');

        return ApiResponse::success($data, $message, $statusCode);
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

        return ApiResponse::error($message, 400, $errors);
    }

    /**
     * Handle unauthorized (401)
     */
    protected function handleUnauthorized(Response $response): Response
    {
        $this->logDebug("Processing unauthorized response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Unauthorized');

        return ApiResponse::unauthorized($message);
    }

    /**
     * Handle forbidden (403)
     */
    protected function handleForbidden(Response $response): Response
    {
        $this->logDebug("Processing forbidden response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Forbidden');

        return ApiResponse::forbidden($message);
    }

    /**
     * Handle not found (404)
     */
    protected function handleNotFound(Response $response): Response
    {
        $this->logDebug("Processing not found response");
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Resource not found');

        return ApiResponse::notFound($message);
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

        return ApiResponse::validationError($errors ?: [], $message);
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

        return ApiResponse::tooManyRequests($message, $retryAfter ? (int)$retryAfter : null);
    }

    /**
     * Handle server errors (5xx)
     */
    protected function handleServerError(Response $response): Response
    {
        $this->logDebug("Processing server error response: " . $response->getStatusCode());
        
        $content = $this->getResponseContent($response);
        $message = $this->extractMessage($content, 'Internal server error');
        $errors = app()->environment('production') ? null : $this->extractErrors($content);

        return ApiResponse::serverError($message, $errors);
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

        return ApiResponse::error($message, $response->getStatusCode(), $errors);
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

        return $content['errors'] ?? $content['error'] ?? null;
    }
}