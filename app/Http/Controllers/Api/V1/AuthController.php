<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create($request->validated());

        Log::info('User registered', [
            'user_id' => $user->id,
            'role' => $user->role,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request) {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            Log::warning('Login failed', [
                'reason' => 'invalid_credentials',
            ]);

            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info('User logged in', [
            'user_id' => $user->id,
            'role' => $user->role,
        ]);

        return response()->json([
            'message' => 'User logged in successfully',
            'user' => new UserResource($user),
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request) {
        if (!$request->user()) {
            Log::warning('Logout failed', [
                'reason' => 'not_authenticated',
            ]);

            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        if (!$request->user()->currentAccessToken()) {
            Log::warning('Logout failed', [
                'user_id' => $request->user()->id,
                'reason' => 'missing_active_token',
            ]);

            return response()->json([
                'message' => 'No active token found',
            ], 401);
        }

        $token = $request->user()->currentAccessToken();

        if (!$token) {
            return response()->json([
                'message' => 'No active token found',
            ], 401);
        }

        $token->delete();

        Log::info('User logged out', [
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'User logged out successfully',
        ], 200);
    }

    public function me(Request $request) {
        Log::debug('Authenticated user requested', [
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'user' => new UserResource($request->user()),
        ], 200);
    }
}
