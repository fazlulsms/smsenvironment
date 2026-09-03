<?php

namespace App\Http\Controllers;

use App\Models\BusinessEntity;
use App\Models\Service;
use App\Support\ServiceCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceController extends Controller
{
    /**
     * Unified Service Catalogue: the global standards/programs/packages plus the
     * legacy services, browsable in one place (the tables stay normalized).
     */
    public function index(Request $request): View
    {
        $all = ServiceCatalogue::all();
        $categoryCounts = ServiceCatalogue::categoryCounts($all);

        $filters = $request->only(['search', 'category', 'type', 'status']);
        $filtered = ServiceCatalogue::filter($all, $filters);

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('services.index', [
            'items' => $items,
            'categoryCounts' => $categoryCounts,
            'total' => $all->count(),
            'types' => ServiceCatalogue::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('services.create', [
            'service' => new Service(['service_type' => Service::TYPE_STANDALONE]),
            'entities' => $this->activeEntities(),
            'enabledEntityIds' => $this->activeEntities()->pluck('id')->all(), // pre-enable all for a new service
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = Service::query()->create($this->validated($request));
        $this->syncComponents($service, $request->input('components', []));
        $this->syncAvailability($service, $request->input('entities'));

        return redirect()->route('services.edit', $service)->with('status', 'Service saved.');
    }

    public function show(Service $service): View
    {
        $service->load('components');

        return view('services.show', compact('service'));
    }

    public function edit(Service $service): View
    {
        $service->load('components', 'businessEntities');

        return view('services.edit', [
            'service' => $service,
            'entities' => $this->activeEntities(),
            'enabledEntityIds' => $service->enabledEntityIds(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request));
        $this->syncComponents($service, $request->input('components', []));
        $this->syncAvailability($service, $request->input('entities'));

        return redirect()->route('services.edit', $service)->with('status', 'Service updated.');
    }

    private function activeEntities()
    {
        return BusinessEntity::query()->where('active', true)->orderByDesc('is_default')->orderBy('name')->get();
    }

    /** Enable/disable this global service per entity via the availability pivot. */
    private function syncAvailability(Service $service, ?array $entityIds): void
    {
        $enabled = collect($entityIds)->map(fn ($id) => (int) $id)->all();
        $sync = $this->activeEntities()->mapWithKeys(fn ($entity) => [
            $entity->id => ['active' => in_array($entity->id, $enabled, true)],
        ])->all();

        $service->businessEntities()->sync($sync);
    }

    public function destroy(Service $service): RedirectResponse
    {
        // Deletion is Super Admin only, even though managing services (create/edit)
        // is open to Admin. This is the independent server-side check.
        Gate::authorize('delete-records');
        $service->delete();

        return redirect()->route('services.index')->with('status', 'Service deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'service_type' => ['nullable', 'in:'.implode(',', array_keys(Service::TYPES))],
            'default_description' => ['nullable', 'string'],
            'default_unit' => ['nullable', 'string', 'max:255'],
            'default_rate' => ['required', 'numeric', 'min:0'],
            'quotation_subject_template' => ['nullable', 'string', 'max:255'],
            'quotation_scope' => ['nullable', 'string'],
            'compliance_note' => ['nullable', 'string'],
            'invoice_description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['service_type'] = $data['service_type'] ?? Service::TYPE_STANDALONE;

        return $data;
    }

    private function syncComponents(Service $service, array $components): void
    {
        $rows = collect($components)
            ->map(function (array $component, int $index) {
                $name = trim((string) ($component['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'description' => $component['description'] ?? null,
                    'default_price' => filled($component['default_price'] ?? null) ? $component['default_price'] : null,
                    'is_active' => filter_var($component['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => filled($component['sort_order'] ?? null) ? (int) $component['sort_order'] : $index + 1,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $service->components()->delete();
        $service->components()->createMany($rows);
    }
}
