<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Request;

class MuninToken
{
    public function handle($request, Closure $next, $guard = null)
    {
        // The token can be sent as a Bearer token, which keeps it out of web server access
        // logs, or as a "token" query parameter
        $token = $request->bearerToken() ?: Request::input('token');

        if($token != config('aperture.munin_token')) {
            return response('Unauthorized', 401)->header('Content-type', 'text/plain');
        }

        return $next($request);
    }
}
