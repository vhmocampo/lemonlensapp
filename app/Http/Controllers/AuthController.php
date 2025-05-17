<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;

class AuthController extends Controller{

    public function google(Request $request)
    {
        $code = $request->input('code');

        // Exchange the code for user info from Google
        $googleUser = Socialite::driver('google')->stateless()->userFromToken(
            $this->exchangeCodeForAccessToken($code)
        );

        // Find or create user
        $user = User::updateOrCreate([
            'email' => $googleUser->getEmail(),
        ], [
            'name' => $googleUser->getName(),
            // Any other fields...
        ]);

        // Create Sanctum token
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    /**
     * Helper to exchange code for Google access token.
     */
    protected function exchangeCodeForAccessToken($code)
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'code' => $code,
        ]);

        $data = $response->json();
        return $data['access_token'] ?? null;
    }
}