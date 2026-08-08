<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::query()
            ->when($request->search, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('services.index', compact('services'));
    }

    public function create(): View
    {
        return view('services.create', ['service' => new Service]);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = Service::query()->create($this->validated($request));

        return redirect()->route('services.edit', $service)->with('status', 'Service saved.');
    }

    public function show(Service $service): View
    {
        return view('services.show', compact('service'));
    }

    public function edit(Service $service): View
    {
        return view('services.edit', compact('service'));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request));

        return redirect()->route('services.edit', $service)->with('status', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('services.index')->with('status', 'Service deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
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

        return $data;
    }
}
