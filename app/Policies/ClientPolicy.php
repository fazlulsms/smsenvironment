<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Clients are shared operational master data: everyone can view/create/edit them.
 * Deletion is Super Admin only (granted via Gate::before) — Admin and Staff cannot
 * delete clients. Cascade safety (a client with real documents cannot be removed)
 * is additionally enforced in ClientController::destroy.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return true;
    }

    public function delete(User $user, Client $client): bool
    {
        // Super Admin only (via Gate::before). No one else deletes clients.
        return false;
    }
}
