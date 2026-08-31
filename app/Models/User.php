<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'avatar_path'])]
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

    /**
     * URL for the avatar, or null when none is set / the file is missing.
     *
     * Served through a route (not a direct /storage link) and returned as a
     * DOMAIN-RELATIVE path. This makes avatars resolve on any host regardless of
     * APP_URL, and works without the public-storage symlink — important on shared
     * hosting (e.g. Hostinger) where symlinks and APP_URL are often unreliable.
     * A version query keyed on updated_at busts the browser cache on change.
     */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path || ! Storage::disk('public')->exists($this->avatar_path)) {
            return null;
        }

        return route('avatar.show', [
            'user' => $this->getKey(),
            'v' => optional($this->updated_at)->timestamp,
        ], absolute: false);
    }

    /** Up-to-two-letter initials used as the avatar fallback. */
    public function initials(): string
    {
        $initials = collect(explode(' ', trim((string) $this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->implode('');

        return mb_strtoupper($initials ?: 'U');
    }
}
