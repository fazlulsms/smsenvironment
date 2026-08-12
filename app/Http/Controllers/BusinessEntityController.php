<?php

namespace App\Http\Controllers;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Support\CurrentEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessEntityController extends Controller
{
    public function overview(): View
    {
        // Counts across every entity — deliberately bypasses the active-entity scope.
        $quotationCounts = Quotation::acrossEntities()->selectRaw('business_entity_id, count(*) c, coalesce(sum(total),0) t')->groupBy('business_entity_id')->get()->keyBy('business_entity_id');
        $invoiceCounts = ProformaInvoice::acrossEntities()->selectRaw('business_entity_id, count(*) c, coalesce(sum(total),0) t')->groupBy('business_entity_id')->get()->keyBy('business_entity_id');
        $clientCounts = Client::acrossEntities()->selectRaw('business_entity_id, count(*) c')->groupBy('business_entity_id')->get()->keyBy('business_entity_id');

        $entities = BusinessEntity::query()->orderByDesc('is_default')->orderBy('name')->get()->map(function ($entity) use ($quotationCounts, $invoiceCounts, $clientCounts) {
            $entity->stat_quotations = (int) ($quotationCounts[$entity->id]->c ?? 0);
            $entity->stat_quoted_value = (float) ($quotationCounts[$entity->id]->t ?? 0);
            $entity->stat_invoices = (int) ($invoiceCounts[$entity->id]->c ?? 0);
            $entity->stat_invoiced_value = (float) ($invoiceCounts[$entity->id]->t ?? 0);
            $entity->stat_clients = (int) ($clientCounts[$entity->id]->c ?? 0);

            return $entity;
        });

        return view('entities.overview', ['entities' => $entities]);
    }

    public function switch(Request $request, CurrentEntity $current): RedirectResponse
    {
        $entity = BusinessEntity::query()
            ->where('active', true)
            ->findOrFail($request->integer('entity_id'));

        $current->switchTo($entity->id);

        // Always land on the dashboard: the previous page may reference a record
        // the newly-selected entity does not own.
        return redirect()->route('dashboard')->with('status', 'Switched to '.$entity->name.'.');
    }
}
