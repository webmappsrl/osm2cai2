<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;


class CasAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!cas()->checkAuthentication()) {
            if ($request->ajax()) {
                return response('Unauthorized.', 401);
            }
            cas()->authenticate();
        }

        session()->put('cas_user', cas()->user());

        return $next($request);
    }
}
