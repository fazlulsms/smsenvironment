<?php

namespace App\Policies;

use App\Models\ProformaInvoice;
use App\Models\User;

/**
 * Proforma invoices are core financial documents. All roles can view, create,
 * edit, duplicate, PDF and email them. Deletion is restricted:
 *   - Draft (never emailed): Admin + Super Admin
 *   - Issued (emailed to a recipient): Super Admin only (strong confirmation in UI)
 *   - Staff: never
 * Deletes are soft (SoftDeletes), so numbering stays monotonic and QR verification
 * of a previously issued document is preserved.
 */
class ProformaInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProformaInvoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProformaInvoice $invoice): bool
    {
        return true;
    }

    public function duplicate(User $user, ProformaInvoice $invoice): bool
    {
        return true;
    }

    public function delete(User $user, ProformaInvoice $invoice): bool
    {
        // Deletion is Super Admin only (they pass via Gate::before). Admin and
        // Staff can never delete a commercial document — not even a draft they
        // created — so they cannot quietly remove offers from reporting.
        return false;
    }
}
