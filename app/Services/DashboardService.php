<?php

namespace App\Services;

use App\Models\AssessmentSchedule;
use App\Models\InvoicePayment;
use App\Models\ProformaInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Management dashboard metrics for the CURRENT business entity within a selected
 * date period. Monetary KPI cards are grouped BY CURRENCY (never summed across
 * currencies); charts and cross-service reports use each invoice's saved
 * BDT-equivalent (total × conversion_rate) so figures stay comparable.
 */
class DashboardService
{
    public function __construct(private Carbon $from, private Carbon $to) {}

    public static function forPeriod(string $preset, ?string $from, ?string $to): self
    {
        $today = Carbon::today();

        [$start, $end] = match ($preset) {
            'today' => [$today->copy(), $today->copy()->endOfDay()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [
                $from ? Carbon::parse($from)->startOfDay() : $today->copy()->startOfMonth(),
                $to ? Carbon::parse($to)->endOfDay() : $today->copy()->endOfDay(),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()], // month
        };

        return new self($start, $end);
    }

    /**
     * Commercial KPIs from NORMALIZED offers (quotation OR proforma invoice, with
     * linked quotation→PI counted once). The primary volume metric is CREATED:
     * every retained (non-deleted) commercial document counts, regardless of
     * whether it was ever emailed or marked sent. Total Invoiced stays PI-only.
     */
    public function invoiceKpis(): array
    {
        $items = (new CommercialReport)->items();

        $created = $items->filter(fn ($i) => $this->inPeriod($i->created_date));
        $won = $items->filter(fn ($i) => $i->status === 'won' && $this->inPeriod($i->status_date));
        $lost = $items->filter(fn ($i) => $i->status === 'lost' && $this->inPeriod($i->status_date));

        return [
            'created' => $this->byCurrencyItems($created),
            'won' => $this->byCurrencyItems($won),
            'lost' => $this->byCurrencyItems($lost),
            // Accounting figure: actual proforma invoice value only.
            'invoiced' => $this->byCurrency(ProformaInvoice::query()->whereBetween('date', [$this->from, $this->to])),
            // Secondary operational indicator: how many of the created offers were
            // emailed through the app or explicitly marked Sent. Won/Lost do NOT
            // count here (being past draft is not "sent"). Not a volume KPI.
            'sent_count' => $created->filter(fn ($i) => $i->status === 'sent' || $i->was_emailed)->count(),
        ];
    }

    private function inPeriod(?Carbon $date): bool
    {
        return $date !== null && $date->between($this->from, $this->to);
    }

    /** Group a collection of normalized commercial items by currency (count + value). */
    private function byCurrencyItems(Collection $items): array
    {
        return $items->groupBy('currency')
            ->map(fn ($rows, $cur) => ['currency' => $cur, 'count' => $rows->count(), 'value' => (float) $rows->sum('value')])
            ->values()->all();
    }

    /** Received (payments in period) + current Due on invoices dated in period, by currency. */
    public function receivableKpis(): array
    {
        $received = InvoicePayment::query()
            ->whereHas('invoice')
            ->whereBetween('received_date', [$this->from, $this->to])
            ->selectRaw('currency, sum(amount) as total')
            ->groupBy('currency')->get()
            ->groupBy(fn ($r) => strtoupper($r->currency ?: 'BDT'))
            ->mapWithKeys(fn ($rows, $cur) => [$cur => (float) $rows->sum('total')])->all();

        $invoices = ProformaInvoice::query()->whereBetween('date', [$this->from, $this->to])
            ->withSum('payments as received_sum', 'amount')->get();

        $due = [];
        $invoicedForCollection = 0.0;
        $receivedForCollection = 0.0;
        foreach ($invoices as $inv) {
            $cur = $inv->payableCurrency();
            $d = round((float) $inv->total - (float) ($inv->received_sum ?? 0), 2);
            $due[$cur] = ($due[$cur] ?? 0) + max(0, $d);
            $invoicedForCollection += $inv->baseTotal();
            $receivedForCollection += ($inv->received_sum ?? 0) * ($inv->baseTotal() > 0 && $inv->total > 0 ? $inv->baseTotal() / $inv->total : 1);
        }

        return [
            'received' => $received,
            'due' => $due,
            'collection_pct' => $invoicedForCollection > 0 ? round($receivedForCollection / $invoicedForCollection * 100) : null,
        ];
    }

    public function scheduleKpis(): array
    {
        $today = Carbon::today();
        $scheduled = AssessmentSchedule::query()
            ->whereBetween('scheduled_from', [$this->from, $this->to])
            ->where('status', '!=', 'cancelled')
            ->withCount('assessors')->get();

        return [
            'scheduled' => $scheduled->count(),
            'completed' => AssessmentSchedule::query()->whereBetween('completed_date', [$this->from, $this->to])->count(),
            'assessment_days' => (int) $scheduled->sum('assessment_days'),
            'assessor_days' => (int) $scheduled->sum(fn ($s) => $s->assessment_days * $s->assessors_count),
            'upcoming' => AssessmentSchedule::query()->where('status', 'completed')
                ->whereNotNull('next_reassessment_date')
                ->whereBetween('next_reassessment_date', [$today, $today->copy()->addDays(90)])->count(),
            'overdue' => AssessmentSchedule::query()->where('status', 'completed')
                ->whereNotNull('next_reassessment_date')
                ->whereDate('next_reassessment_date', '<', $today)->count(),
        ];
    }

    /** Last 12 months invoiced value in BDT-equivalent (for the trend chart). */
    public function monthlyInvoiced(): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths(11);
        $rows = ProformaInvoice::query()->whereDate('date', '>=', $start)->get(['date', 'total', 'currency', 'conversion_rate']);

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $months[$m->format('Y-m')] = ['label' => $m->format('M'), 'value' => 0.0];
        }
        foreach ($rows as $inv) {
            $key = Carbon::parse($inv->date)->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['value'] += $inv->baseTotal();
            }
        }

        return array_values($months);
    }

    /**
     * Service-wise report (BDT-equivalent), date filtered. Offers Created + Won
     * come from normalized commercial items (no linked quotation/PI double count);
     * Received + Due are proforma-invoice only. "Offers" counts every retained
     * document by its created/document date — not sent/email status.
     */
    public function serviceReport(): array
    {
        $report = [];
        $row = function (&$report, $service) {
            $report[$service] ??= ['service' => $service, 'offers' => 0, 'won_value' => 0.0, 'received' => 0.0, 'due' => 0.0];
        };

        foreach ((new CommercialReport)->items() as $i) {
            if ($this->inPeriod($i->created_date)) {
                $row($report, $i->service);
                $report[$i->service]['offers']++;
            }
            if ($i->status === 'won' && $this->inPeriod($i->status_date)) {
                $row($report, $i->service);
                $report[$i->service]['won_value'] += $i->base;
            }
        }

        $invoices = ProformaInvoice::query()->whereBetween('date', [$this->from, $this->to])
            ->with('items.service')->withSum('payments as received_sum', 'amount')->get();
        foreach ($invoices as $inv) {
            $service = $inv->items->first()?->service?->short_name
                ?? $inv->items->first()?->service?->name
                ?? ($inv->charge_for ?: 'Other');
            $row($report, $service);
            $factor = $inv->total > 0 ? $inv->baseTotal() / $inv->total : 1;
            $report[$service]['received'] += ($inv->received_sum ?? 0) * $factor;
            $report[$service]['due'] += max(0, ((float) $inv->total - (float) ($inv->received_sum ?? 0))) * $factor;
        }

        usort($report, fn ($a, $b) => $b['won_value'] <=> $a['won_value']);

        return array_values($report);
    }

    /** Assessor operational report for the period. */
    public function assessorReport(): array
    {
        $schedules = AssessmentSchedule::query()
            ->whereBetween('scheduled_from', [$this->from, $this->to])
            ->where('status', '!=', 'cancelled')
            ->with('assessors')->get();

        $report = [];
        foreach ($schedules as $s) {
            foreach ($s->assessors as $a) {
                $report[$a->id] ??= ['assessor' => $a->name, 'assignments' => 0, 'assessment_days' => 0, 'completed' => 0];
                $report[$a->id]['assignments']++;
                $report[$a->id]['assessment_days'] += (int) $s->assessment_days;
                if ($s->status === 'completed') {
                    $report[$a->id]['completed']++;
                }
            }
        }

        usort($report, fn ($x, $y) => $y['assessment_days'] <=> $x['assessment_days']);

        return array_values($report);
    }

    private function byCurrency($query): array
    {
        // Group by NORMALIZED currency so null/'' and 'BDT' collapse into one line.
        return $query->selectRaw('currency, count(*) as cnt, sum(total) as total')
            ->groupBy('currency')->get()
            ->groupBy(fn ($r) => strtoupper($r->currency ?: 'BDT'))
            ->map(fn ($rows, $cur) => ['currency' => $cur, 'count' => (int) $rows->sum('cnt'), 'value' => (float) $rows->sum('total')])
            ->values()->all();
    }

    public function period(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }
}
