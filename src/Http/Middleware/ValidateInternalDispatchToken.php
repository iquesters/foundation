<?php

namespace Iquesters\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateInternalDispatchToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedToken = (string) (
            $request->header('X-Internal-Token')
            ?? $request->bearerToken()
            ?? $request->input('token')
        );

        $expectedToken = (string) env('FOUNDATION_INTERNAL_QUEUE_DISPATCH_TOKEN', '');

        if ($expectedToken === '' || $providedToken !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized token',
            ], 403);
        }

        return $next($request);
    }
}