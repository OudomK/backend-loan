<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login: email + password. Returns token and user (name, role) for many users/roles.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = $request->user();
        $user->tokens()->where('name', 'app')->delete(); // one token per device
        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->first() ?? $user->role ?? 'Staff',
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->roles->flatMap(fn($role) => $role->permissions)->pluck('name')
                    ->merge($user->permissions->pluck('name'))
                    ->unique()
                    ->values(),
            ],
        ]);
    }

    /**
     * Logout: revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Fire standard logout event for auditing
        event(new \Illuminate\Auth\Events\Logout('sanctum', $user));

        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
