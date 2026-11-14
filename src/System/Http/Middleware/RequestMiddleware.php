<?php

namespace Iquesters\Foundation\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Iquesters\Foundation\System\Traits\Loggable;

class RequestMiddleware
{
    use Loggable;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Details at debug level; ResponseMiddleware writes the one-line INFO summary.
        $this->logDebug("{$request->method()} {$request->fullUrl()}");

        if ($this->isApiRequest($request)) {
            $this->logRequestDetails($request);
        }

        $this->processRequest($request);

        return $next($request);
    }

    /**
     * Process the incoming request
     *
     * @param Request $request
     * @return void
     */
    protected function processRequest(Request $request): void
    {
        // Normalize request data for API routes
        if ($this->isApiRequest($request)) {
            $this->normalizeApiRequest($request);
        }

        // Add custom headers if needed
        $this->addCustomHeaders($request);

        // Validate request structure if needed
        // $this->validateRequestStructure($request);
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
     * Log request details
     *
     * @param Request $request
     * @return void
     */
    protected function logRequestDetails(Request $request): void
    {
        // Sensitive headers and fields are removed by getSafeHeaders()/getSafeRequestData().
        $this->logDebug('API request details', array_filter([
            'headers' => $this->getSafeHeaders($request),
            'query' => $request->query->count() > 0 ? $request->query->all() : null,
            'body' => $request->getContent() ? $this->getSafeRequestData($request) : null,
        ]));
    }

    /**
     * Get safe headers (excluding sensitive information)
     *
     * @param Request $request
     * @return array
     */
    protected function getSafeHeaders(Request $request): array
    {
        $headers = $request->headers->all();
        $sensitiveHeaders = ['authorization', 'cookie', 'set-cookie', 'x-csrf-token'];

        return array_filter($headers, function($key) use ($sensitiveHeaders) {
            return !in_array(strtolower($key), $sensitiveHeaders);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Get safe request data (excluding sensitive fields)
     *
     * @param Request $request
     * @return array
     */
    protected function getSafeRequestData(Request $request): array
    {
        $data = $request->all();
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        return $data;
    }

    /**
     * Normalize API request data
     *
     * @param Request $request
     * @return void
     */
    protected function normalizeApiRequest(Request $request): void
    {
        // Ensure consistent request structure
        // For example, convert offset/length to page/per_page if needed
        
        if ($request->has('offset') && $request->has('length')) {
            $offset = (int) $request->get('offset', 0);
            $length = (int) $request->get('length', 10);
            
            // Calculate page number from offset
            $page = floor($offset / $length) + 1;
            
            // Merge calculated values into request
            $request->merge([
                'page' => $page,
                'per_page' => $length,
            ]);
            
            $this->logDebug("Normalized pagination: offset={$offset}, length={$length} => page={$page}, per_page={$length}");
        }

        // Trim string inputs
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $this->trimStrings($request);
        }
    }

    /**
     * Trim string values in request
     *
     * @param Request $request
     * @return void
     */
    protected function trimStrings(Request $request): void
    {
        $input = $request->all();
        
        array_walk_recursive($input, function(&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        });
        
        $request->replace($input);
    }

    /**
     * Add custom headers to request
     *
     * @param Request $request
     * @return void
     */
    protected function addCustomHeaders(Request $request): void
    {
        // Add request ID for tracking
        if (!$request->headers->has('X-Request-ID')) {
            $requestId = $this->generateRequestId();
            $request->headers->set('X-Request-ID', $requestId);
            $this->logDebug("Generated Request ID: {$requestId}");
        }

        // Add timestamp
        if (!$request->headers->has('X-Request-Time')) {
            $request->headers->set('X-Request-Time', now()->toIso8601String());
        }
    }

    /**
     * Generate unique request ID
     *
     * @return string
     */
    protected function generateRequestId(): string
    {
        return sprintf(
            '%s-%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }

    /**
     * Validate request structure (if needed)
     *
     * @param Request $request
     * @return void
     */
    protected function validateRequestStructure(Request $request): void
    {
        // Add custom validation logic here if needed
        // For example, ensure certain headers are present
        
        if ($this->isApiRequest($request)) {
            // Ensure Accept header is set
            if (!$request->headers->has('Accept')) {
                $request->headers->set('Accept', 'application/json');
            }
        }
    }
}