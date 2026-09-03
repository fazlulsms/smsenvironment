<?php

namespace App\Services;

use App\Models\DocumentEmailDelivery;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Normalizes quotations + proforma invoices into a single set of COMMERCIAL
 * OFFERS for management reporting. Business rules:
 *   - Either a quotation OR a proforma invoice can be the offer.
 *   - A proforma invoice linked to a quotation (proforma_invoices.quotation_id)
 *     represents the SAME engagement — the quotation is dropped and the invoice
 *     is the effective offer (its value + status win). No double counting.
 *   - Unlinked quotations and invoices each count separately (no fuzzy matching).
 *   - "Created" is every retained (non-soft-deleted) document — this is the
 *     primary commercial-volume signal. Both models use SoftDeletes, so the
 *     default query already excludes deleted records.
 *   - "Sent" (status past draft OR actually emailed) is retained only as a
 *     secondary/operational indicator; it does NOT gate whether the offer exists.
 * Runs in the current entity context (both models are entity-scoped). Payments,
 * received and due are NOT computed here — they remain proforma-invoice only.
 *
 * @return Collection<int, object> items with: type, id, currency, value, base,
 *                                 status, is_sent, created_date, sent_date, status_date, service, client_id, client_name
 */
class CommercialReport
{
    public function items(): Collection
    {
        // One-query lookups of the last successful email per document (avoids N+1).
        $piEmail = $this->lastSentEmails('proforma_invoice');
        $qtEmail = $this->lastSentEmails('quotation');

        $invoices = ProformaInvoice::query()->with('items.service')->get();
        $linkedQuotationIds = $invoices->pluck('quotation_id')->filter()->unique()->all();

        // toBase() so merge() below uses base-collection semantics — an empty
        // Eloquent Collection would otherwise call getKey() on our stdClass items.
        $items = $invoices->map(fn (ProformaInvoice $inv) => $this->item(
            'invoice', $inv, $inv->payableCurrency(), (float) $inv->total, $inv->baseTotal(), $piEmail[$inv->id] ?? null
        ))->toBase();

        $quotations = Quotation::query()->with('items.service')
            ->whereNotIn('id', $linkedQuotationIds)->get();

        return $items->merge($quotations->map(fn (Quotation $q) => $this->item(
            'quotation', $q, 'BDT', (float) $q->total, (float) $q->total, $qtEmail[$q->id] ?? null
        )))->values();
    }

    private function item(string $type, $doc, string $currency, float $value, float $base, ?string $lastEmailAt): object
    {
        $isSent = $doc->commercial_status !== 'draft' || $lastEmailAt !== null;

        // Sent date: explicit status change > last email > document date (when sent).
        if ($doc->commercial_status !== 'draft' && $doc->status_updated_at) {
            $sentDate = $doc->status_updated_at;
        } elseif ($lastEmailAt) {
            $sentDate = Carbon::parse($lastEmailAt);
        } else {
            $sentDate = $isSent ? $doc->date : null;
        }

        return (object) [
            'type' => $type,
            'id' => $doc->id,
            'currency' => strtoupper($currency ?: 'BDT'),
            'value' => $value,
            'base' => $base,
            'status' => $doc->commercial_status,
            'is_sent' => $isSent,
            // Emailed through the app (used for the "sent via system" secondary
            // figure, so Won/Lost don't inflate it just by being past draft).
            'was_emailed' => $lastEmailAt !== null,
            // Created date: the business document date (issue date), falling back
            // to created_at only if a record somehow has no date.
            'created_date' => $doc->date ? Carbon::parse($doc->date) : ($doc->created_at ? Carbon::parse($doc->created_at) : null),
            'sent_date' => $sentDate,
            'status_date' => $doc->status_updated_at ?: $doc->date,
            'service' => $doc->items->first()?->service?->short_name
                ?? $doc->items->first()?->service?->name
                ?? ($doc->charge_for ?? $doc->charge_title ?? 'Other'),
            'client_id' => $doc->client_id,
            'client_name' => $doc->client_snapshot['company_name'] ?? null,
        ];
    }

    /** @return array<int,string> document_id => last sent_at */
    private function lastSentEmails(string $documentType): array
    {
        return DocumentEmailDelivery::query()
            ->where('document_type', $documentType)->where('status', 'sent')
            ->selectRaw('document_id, max(sent_at) as sent_at')
            ->groupBy('document_id')->pluck('sent_at', 'document_id')->all();
    }
}
