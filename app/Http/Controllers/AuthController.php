<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use BezhanSalleh\FilamentShield\Support\Utils;

class AuthController extends Controller
{
    /**
     * Login: email + password. Returns token and user (name, role) for many users/roles.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required',
        ]);

        $login = trim((string) $request->input('login'));
        $password = $request->input('password');

        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $userModel = \App\Models\User::where($loginField, $login)->first();

        if (!$userModel) {
            activity('auth')
                ->withProperties([
                    'login' => $login,
                    'login_field' => $loginField,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('Failed login attempt: User not found');

            throw ValidationException::withMessages([
                'login' => ['ឈ្មោះគណនី ឬ អ៊ីមែលមិនត្រឹមត្រូវទេ'],
            ]);
        }

        if (!Auth::attempt([$loginField => $login, 'password' => $password])) {
            activity('auth')
                ->withProperties([
                    'login' => $login,
                    'login_field' => $loginField,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('Failed login attempt: Incorrect password');

            throw ValidationException::withMessages([
                'password' => ['លេខសម្ងាត់មិនត្រឹមត្រូវទេ'],
            ]);
        }

        $user = $request->user();
        $user->tokens()->where('name', 'app')->delete(); // one token per device
        $token = $user->createToken('app')->plainTextToken;

        $role = $user->role ?? 'Staff';
        $roles = collect();
        $permissions = collect();
        try {
            if (method_exists($user, 'roles') && $user->relationLoaded('roles') === false) {
                $user->load('roles');
            }
            $roles = method_exists($user, 'effectiveRoleNames')
                ? $user->effectiveRoleNames()
                : ($user->roles?->pluck('name') ?? collect());
            if ($roles->isNotEmpty()) {
                $role = $roles->first();
            }
            $permissions = method_exists($user, 'effectivePermissionNames')
                ? $user->effectivePermissionNames()
                : ($user->roles?->flatMap(fn ($r) => $r->permissions ?? collect())->pluck('name')
                    ->merge($user->permissions?->pluck('name') ?? collect())
                    ->unique()
                    ->values() ?? collect());
        } catch (\Throwable $e) {
            // Spatie not set up or no roles: use $user->role only
        }

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'roles' => $roles,
                'permissions' => $permissions,
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
    /**
     * Generate SSO Token for Admin Panel Auto-login
     */
    public function getSsoUrl(Request $request): JsonResponse
    {
        $user = $request->user();
        $superAdminRole = Utils::getSuperAdminName();
        $roleNames = method_exists($user, 'effectiveRoleNames')
            ? $user->effectiveRoleNames()->map(fn (string $role): string => strtolower($role))
            : collect($user?->roles?->pluck('name') ?? [])->map(fn (string $role): string => strtolower($role));

        if (! $user || ! $roleNames->intersect([strtolower($superAdminRole), 'admin'])->isNotEmpty()) {
            abort(403, 'Only admin users can access the Admin Panel.');
        }

        $expiresAt = now()->addMinutes(5);
        $payload = json_encode([
            'user_id' => $user->id,
            'expires_at' => $expiresAt->timestamp,
            'nonce' => \Illuminate\Support\Str::random(16),
        ], JSON_THROW_ON_ERROR);
        $encrypted = \Illuminate\Support\Facades\Crypt::encryptString($payload);
        $token = rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');

        activity('auth')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'expires_at' => $expiresAt->toIso8601String(),
            ])
            ->log('Generated admin SSO token');

        return response()->json([
            'token' => $token,
            'url' => route('admin.sso', ['token' => $token]),
        ]);
    }
}
