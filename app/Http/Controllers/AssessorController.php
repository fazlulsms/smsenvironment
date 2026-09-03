<?php

namespace App\Http\Controllers;

use App\Models\Assessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessorController extends Controller
{
    public function index(): View
    {
        return view('assessors.index', [
            'assessors' => Assessor::query()->orderByDesc('is_active')->orderBy('name')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('assessors.create', ['assessor' => new Assessor(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Assessor::query()->create($this->validated($request));

        return redirect()->route('assessors.index')->with('status', 'Assessor added.');
    }

    public function edit(Assessor $assessor): View
    {
        return view('assessors.edit', ['assessor' => $assessor]);
    }

    public function update(Request $request, Assessor $assessor): RedirectResponse
    {
        $assessor->update($this->validated($request));

        return redirect()->route('assessors.index')->with('status', 'Assessor updated.');
    }

    /** Deactivate/activate — preferred over deletion for assessors with history. */
    public function toggleActive(Assessor $assessor): RedirectResponse
    {
        $assessor->update(['is_active' => ! $assessor->is_active]);

        return back()->with('status', $assessor->is_active ? 'Assessor activated.' : 'Assessor deactivated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
