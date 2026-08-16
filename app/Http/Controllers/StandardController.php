<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use App\Models\Standard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Manage a global catalogue item (a Standard / program / service / package). These
 * are the records documents + the AI resolver use; editing here is authoritative
 * for NEW documents only — saved documents keep their snapshot.
 */
class StandardController extends Controller
{
    public function create(): View
    {
        return view('standards.create', [
            'standard' => new Standard(['active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['service_category_id'], $data['code'] ?: $data['name']);

        $standard = Standard::query()->create($data);

        return redirect()->route('catalogue-standards.edit', $standard)->with('status', 'Catalogue item saved.');
    }

    public function edit(Standard $standard): View
    {
        return view('standards.edit', [
            'standard' => $standard,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Standard $standard): RedirectResponse
    {
        // slug is stable — never rewritten, so historical resolution stays intact.
        $standard->update($this->validated($request));

        return redirect()->route('catalogue-standards.edit', $standard)->with('status', 'Catalogue item updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_scope' => ['nullable', 'string'], // one component per line — packages only
            'display_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['display_order'] = $data['display_order'] ?? 0;

        return $data;
    }

    private function uniqueSlug(int $categoryId, string $base): string
    {
        $slug = Str::slug($base) ?: 'item';
        $candidate = $slug;
        $n = 1;

        while (Standard::query()->where('service_category_id', $categoryId)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.(++$n);
        }

        return $candidate;
    }

    private function categories()
    {
        return ServiceCategory::query()->active()->ordered()->get(['id', 'name']);
    }
}
