<?php

namespace Iquesters\Foundation\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Iquesters\Foundation\System\Traits\AutoLogger;

class ResponseMiddleware
{
    use AutoLogger;

    /**
     * Handle an outgoing response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->logMethodStart("==================================================");

        $response = $next($request);
        // $this->logDebug("Response from controller");
        // $this->logDebug("--------------------------------------------------");
        // $this->logDebug($response);
        // $this->logDebug("--------------------------------------------------");

        $this->logDebug("Processing response...");

        // do your response processing here
        $this->logDebug("No processing on response done now.");


        $this->logDebug("Processing response done.");
        $this->logDebug("--------------------------------------------------");

        $this->logMethodEnd("##################################################");
        // Ending with ######
        // This should always be the last middleware

        return $response;
    }
}
