<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

class OptionalSanctumAuth
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
        // Attempt to authenticate the user, but don't require it
        if ($request->bearerToken()) {
            $model = Sanctum::$personalAccessTokenModel;
            $accessToken = $model::findToken($request->bearerToken());
            
            if ($accessToken && !$accessToken->expired()) {
                $user = $accessToken->tokenable;
                if ($user) {
                    auth()->setUser($user);
                }
            }
        }
        
        // Continue with the request, even if authentication failed
        return $next($request);
    }
}