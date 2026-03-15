<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login (creates an authenticated session)
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $credentials = [
            'email'    => $request->email,
            'password' => $request->password,
        ];

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $user = Auth::user();

        if ($user->status !== 'ACTIVE') {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data'    => [
                'user_id'   => $user->user_id,
                'full_name' => $user->full_name,
                'email'     => $user->email,
                'role'      => $user->role,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout (ends the current session)
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * GET /api/auth/me (current user info)
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data'    => [
                'user_id'    => $user->user_id,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'role'       => $user->role,
                'status'     => $user->status,
                'created_at' => $user->created_at,
            ],
        ]);
    }
}
