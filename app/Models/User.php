<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    /**
     * Roles in ascending privilege order, with their display labels.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        self::ROLE_STAFF => 'Staff',
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_SUPER_ADMIN => 'Super Admin',
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
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** True for Admins only (not Super Admins). Use hasAdminAccess() for "admin or above". */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF || ! in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    /** Admin or Super Admin — the "management" tier. */
    public function hasAdminAccess(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Staff';
    }

    /** Soft badge class used by the Office UI. */
    public function roleBadgeClass(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'b-ok',
            self::ROLE_ADMIN => 'b-info',
            default => 'b-neutral',
        };
    }
}
