<?php

namespace App\Http\Controllers;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Scopes\BusinessEntityScope;
use App\Services\ClientInformationExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        // Clients are a global master — show all, with totals across every entity.
        $globally = fn ($query) => $query->withoutGlobalScope(BusinessEntityScope::class);

        $clients = Client::query()
            ->withCount([
                'quotations as quotations_count' => $globally,
                'proformaInvoices as proforma_invoices_count' => $globally,
            ])
            ->when($request->search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('company_name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('clients.create', ['client' => new Client]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = Client::query()->create($this->validated($request));

        return redirect()->route('clients.show', $client)->with('status', 'Client saved.');
    }

    public function smartPaste(Request $request, ClientInformationExtractor $extractor): JsonResponse
    {
        $data = $request->validate([
            'raw_text' => ['required', 'string', 'max:5000'],
        ]);

        $result = $extractor->extractWithMetadata($data['raw_text']);
        $client = $result['data'];
        $status = $result['source'] === 'none' ? 422 : 200;

        return response()->json([
            'message' => $result['message'],
            'data' => $client,
            'source' => $result['source'],
            'provider' => $result['provider'],
            'duplicates' => $this->possibleDuplicates($client),
        ], $status);
    }

    public function smartStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'parent_company' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'create_anyway' => ['nullable', 'boolean'],
        ]);

        $duplicates = $this->possibleDuplicates($data);

        if ($duplicates !== [] && ! $request->boolean('create_anyway')) {
            return response()->json([
                'message' => 'Possible existing client found.',
                'duplicates' => $duplicates,
            ], 409);
        }

        unset($data['create_anyway']);

        $client = Client::query()->create($data);

        return response()->json([
            'message' => 'Client saved.',
            'client' => $this->clientOption($client),
        ], 201);
    }

    public function show(Client $client): View
    {
        // Recent documents for the CURRENT entity only (safely clickable).
        $client->load([
            'quotations' => fn ($query) => $query->with('emailDeliveries')->latest()->limit(8),
            'proformaInvoices' => fn ($query) => $query->with('emailDeliveries')->latest()->limit(8),
        ]);

        // Business activity broken down by entity (clients are shared globally).
        $qStats = Quotation::acrossEntities()->where('client_id', $client->id)
            ->selectRaw('business_entity_id, count(*) c, coalesce(sum(total),0) t')->groupBy('business_entity_id')->get()->keyBy('business_entity_id');
        $iStats = ProformaInvoice::acrossEntities()->where('client_id', $client->id)
            ->selectRaw('business_entity_id, count(*) c, coalesce(sum(total),0) t')->groupBy('business_entity_id')->get()->keyBy('business_entity_id');

        $activity = BusinessEntity::query()->orderByDesc('is_default')->orderBy('name')->get()->map(fn ($entity) => [
            'entity' => $entity,
            'quotations' => (int) ($qStats[$entity->id]->c ?? 0),
            'quoted' => (float) ($qStats[$entity->id]->t ?? 0),
            'invoices' => (int) ($iStats[$entity->id]->c ?? 0),
            'invoiced' => (float) ($iStats[$entity->id]->t ?? 0),
        ]);

        return view('clients.show', [
            'client' => $client,
            'activity' => $activity,
            'totalQuotations' => (int) $qStats->sum('c'),
            'totalInvoices' => (int) $iStats->sum('c'),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request));

        return redirect()->route('clients.show', $client)->with('status', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Client deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'parent_company' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function possibleDuplicates(array $data): array
    {
        $normalized = ClientInformationExtractor::normalizeCompany($data['company_name'] ?? null);
        $email = $data['email'] ?? null;

        if (! $normalized && ! $email) {
            return [];
        }

        return Client::query()
            ->when($email, fn ($query) => $query->orWhere('email', $email))
            ->when($normalized, fn ($query) => $query->orWhereNotNull('company_name'))
            ->get()
            ->filter(function (Client $client) use ($normalized, $email) {
                return ($email && $client->email === $email)
                    || ($normalized && ClientInformationExtractor::normalizeCompany($client->company_name) === $normalized);
            })
            ->map(fn (Client $client) => $this->clientOption($client))
            ->values()
            ->all();
    }

    private function clientOption(Client $client): array
    {
        return [
            'id' => $client->id,
            'company_name' => $client->company_name,
            'contact_person' => $client->contact_person,
            'email' => $client->email,
            'label' => $client->company_name.($client->contact_person ? ' - '.$client->contact_person : ''),
        ];
    }
}
