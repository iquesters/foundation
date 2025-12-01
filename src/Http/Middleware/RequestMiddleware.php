<?php
namespace Iquesters\Foundation\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Starting with ######
        // This should always be the first middleware
        Log::debug("##################################################");
        Log::debug("Start of RequestMiddleware...");
        Log::debug("--------------------------------------------------");
        Log::debug("Request full URL = " . $request->fullUrl());
        Log::debug("--------------------------------------------------");
        // Log::debug("Request from client");
        // Log::debug("--------------------------------------------------");
        // Log::debug($request);
        // Log::debug("--------------------------------------------------");

        Log::debug("Processing request...");

        // do your request processing here

        Log::debug("Processing request done.");
        Log::debug("--------------------------------------------------");
        Log::debug("Forwarding to next...");
        Log::debug("End of RequestMiddleware.");
        Log::debug("==================================================");
        return $next($request);
    }
}
