<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Request;

class MuninToken
{
    public function handle($request, Closure $next, $guard = null)
    {
        if(Request::input('token') != env('MUNIN_TOKEN')) {
            return response('Unauthorized', 401)->header('Content-type', 'text/plain');
        }

        return $next($request);
    }
}
