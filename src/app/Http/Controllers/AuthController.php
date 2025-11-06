<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * POST /api/register
     * Creates a new user and returns a JWT token.
     */
    public function register(Request $request)
    {
        // Validate incoming payload (returns 422 with errors if invalid)
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        // Create user with hashed password
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Issue JWT for the new user
        $token = JWTAuth::fromUser($user);

        // Return a consistent JSON shape
        return response()->json([
            'message' => 'Registered successfully.',
            'token'   => $token,
        ], 201);
    }

    /**
     * POST /api/login
     * Authenticates user and returns a JWT token.
     * Note: /api/login is protected by 'throttle:login' (anti-bruteforce).
     */
    public function login(Request $request)
    {
        // Basic validation for login payload
        $request->validate([
            'email'      => 'required|string|email',
            'password'   => 'required|string',
            'rememberMe' => 'sometimes|boolean',
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt authentication (no TTL yet — just to verify creds)
        if (! $token = auth()->attempt($credentials)) {
            // IMPORTANT: return a clear, consistent message for the frontend
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Apply TTL after successful auth:
        // tymon/jwt-auth expects TTL in minutes
        $ttlMinutes = $request->boolean('rememberMe')
            ? 60 * 24 * 14  // 14 days
            : 60;           // 60 minutes

        // Re-issue token with the desired TTL
        $token = auth()->setTTL($ttlMinutes)->attempt($credentials);

        // Optionally expose expiry in seconds to the frontend
        return response()->json([
            'message'    => 'Logged in successfully.',
            'token'      => $token,
            'expires_in' => $ttlMinutes * 60, // seconds
        ]);
    }

    /**
     * GET /api/me
     * Returns the authenticated user.
     * Requires middleware auth:api (JWT guard).
     */
    public function me()
    {
        // auth()->user() is populated by JWT middleware
        return response()->json(auth()->user());
    }

    /**
     * POST /api/logout
     * Invalidates the current token (blacklist if enabled).
     */
    public function logout()
    {
        // Invalidate current JWT (if blacklist is enabled in config/jwt.php)
        auth()->logout();

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }
}
