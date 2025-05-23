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
            
            if ($accessToken) {
                // Check if token is expired, but only if expiration is enabled in config
                $tokenExpired = false;
                if (config('sanctum.expiration')) {
                    $tokenExpired = $accessToken->created_at->lte(now()->subMinutes(config('sanctum.expiration')));
                }
                
                if (!$tokenExpired) {
                    $user = $accessToken->tokenable;
                    if ($user) {
                        auth()->setUser($user);
                    }
                }
            }
        }
        
        // Continue with the request, even if authentication failed
        return $next($request);
    }
}