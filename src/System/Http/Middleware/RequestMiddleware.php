<?php

namespace Iquesters\Foundation\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Iquesters\Foundation\System\Traits\AutoLogger;

class RequestMiddleware
{
    use AutoLogger;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Starting with ######
        // This should always be the first middleware
        $this->logMethodStart("##################################################");

        $this->logDebug("Request full URL = " . $request->fullUrl());
        $this->logDebug("--------------------------------------------------");
        // $this->logDebug("Request from client");
        // $this->logDebug("--------------------------------------------------");
        // $this->logDebug($request);
        // $this->logDebug("--------------------------------------------------");

        $this->logDebug("Processing request...");

        // do your request processing here
        $this->logDebug("No processing on request done now.");


        $this->logDebug("Processing request done.");
        $this->logDebug("--------------------------------------------------");
        $this->logDebug("Forwarding to next...");

        $this->logMethodEnd("==================================================");

        return $next($request);
    }
}
