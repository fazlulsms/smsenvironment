<?php

namespace App\Policies;

use App\Models\User;

/**
 * User administration is Super Admin only (granted via Gate::before). Every method
 * here denies non-Super-Admins. Last-Super-Admin protection and self-downgrade
 * rules are enforced in UserController, because Super Admins bypass policies and
 * those safety rules must apply even to them.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
