<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, Auditable;

    public function canAccessPanel(Panel $panel): bool
    {
        // Check for either Spatie roles OR the legacy 'role' string column
        return $this->roles()->exists() || !empty($this->role);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? asset('storage/' . $this->avatar_url) : null;
    }

    public function effectiveRoleNames(): Collection
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')
            : $this->roles()->pluck('name');

        $legacyRole = trim((string) ($this->role ?? ''));

        if ($legacyRole !== '') {
            $roles->push($legacyRole);
        }

        return $roles
            ->filter()
            ->unique(fn (string $role): string => strtolower($role))
            ->values();
    }

    public function effectivePermissionNames(): Collection
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->merge($this->legacyRolePermissionNames())
            ->unique()
            ->values();
    }

    public function hasEffectivePermission(string $ability): bool
    {
        $superAdminRole = Utils::getSuperAdminName();

        if ($this->hasRole($superAdminRole) || strtolower((string) ($this->role ?? '')) === strtolower($superAdminRole)) {
            return true;
        }

        return $this->effectivePermissionNames()->contains($ability);
    }

    private function legacyRolePermissionNames(): Collection
    {
        $legacyRole = trim((string) ($this->role ?? ''));

        if ($legacyRole === '') {
            return collect();
        }

        $roleModel = config('permission.models.role');

        if (! is_string($roleModel) || ! class_exists($roleModel)) {
            return collect();
        }

        $roles = $roleModel::query()
            ->with('permissions')
            ->whereRaw('LOWER(name) = ?', [strtolower($legacyRole)])
            ->get();

        return $roles->flatMap(fn ($role) => $role->permissions)->pluck('name')->unique()->values();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'role',
        'password',
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function auditLogExcept(): array
    {
        return [
            'password',
            'remember_token',
        ];
    }
}
