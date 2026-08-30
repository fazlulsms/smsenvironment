<?php

namespace App\Policies;

use App\Models\ServiceInquiry;
use App\Models\User;

/**
 * Website inquiries are operational leads. All roles may view them, update their
 * status, and bridge them into clients/quotations. Deletion (removing a lead /
 * audit record) is Super Admin only, via Gate::before.
 */
class ServiceInquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceInquiry $inquiry): bool
    {
        return true;
    }

    public function update(User $user, ServiceInquiry $inquiry): bool
    {
        return true;
    }

    public function delete(User $user, ServiceInquiry $inquiry): bool
    {
        // Super Admin only (via Gate::before).
        return false;
    }
}
