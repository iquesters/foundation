<?php

namespace Iquesters\Foundation\Http\Middleware;

// use App\Models\User;
// use App\Models\UserMetas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        Log::debug("==================================================");
        Log::debug("Start of ResponseMiddleware...");
        Log::debug("--------------------------------------------------");
        Log::debug("No processing on response needed.");
        Log::debug("--------------------------------------------------");
        // Log::debug("Response from controller");
        // Log::debug("--------------------------------------------------");
        // // Log::debug($response);
        // Log::debug("--------------------------------------------------");
        Log::debug("Processing response...");

        // do your response processing here

        // force redirection of user data
        // if (Auth::check()) {
        //     $user = User::find(Auth::user()->id);

        //     $redirect_url = $user->meta()?->redirect_url ?? null;

        //     if (isset($redirect_url)) {
        //         UserMetas::where([
        //             'ref_user' => Auth::user()->id,
        //             'key' => 'redirect_url',
        //         ])->delete();
        //         return redirect($redirect_url);
        //     }
        // }

        Log::debug("Processing response done.");
        Log::debug("--------------------------------------------------");

        Log::debug("End of ResponseMiddleware.");
        // Ending with ######
        // This should always be the last middleware
        Log::debug("##################################################");

        return $response;
    }
}
