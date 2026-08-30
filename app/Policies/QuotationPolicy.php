<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

/**
 * Quotations are core operational documents. All roles can view, create, edit,
 * duplicate, PDF and email them. Deletion is restricted:
 *   - Draft (never emailed): Admin + Super Admin
 *   - Issued (emailed to a recipient): Super Admin only (strong confirmation in UI)
 *   - Staff: never
 * Deletes are soft (SoftDeletes), so numbering stays monotonic and QR verification
 * of a previously issued document is preserved.
 */
class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return true;
    }

    public function duplicate(User $user, Quotation $quotation): bool
    {
        return true;
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        // Super Admin passes via Gate::before (may delete drafts or issued docs).
        // Admin may delete only drafts (never-emailed). Staff never.
        return $user->hasAdminAccess() && ! $quotation->wasEmailed();
    }
}
