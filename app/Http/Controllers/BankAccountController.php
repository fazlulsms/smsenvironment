<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(): View
    {
        return view('bank_accounts.index', [
            'bankAccounts' => BankAccount::query()->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('bank_accounts.create', ['bankAccount' => new BankAccount]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->clearDefaultIfNeeded($data);
        $bankAccount = BankAccount::query()->create($data);

        return redirect()->route('bank-accounts.edit', $bankAccount)->with('status', 'Bank account saved.');
    }

    public function show(BankAccount $bankAccount): View
    {
        return view('bank_accounts.show', compact('bankAccount'));
    }

    public function edit(BankAccount $bankAccount): View
    {
        return view('bank_accounts.edit', compact('bankAccount'));
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $data = $this->validated($request);
        $this->clearDefaultIfNeeded($data, $bankAccount);
        $bankAccount->update($data);

        return redirect()->route('bank-accounts.edit', $bankAccount)->with('status', 'Bank account updated.');
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->delete();

        return redirect()->route('bank-accounts.index')->with('status', 'Bank account deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'routing_number' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_default'] = $request->boolean('is_default');

        if (! $data['is_active']) {
            $data['is_default'] = false;
        }

        return $data;
    }

    private function clearDefaultIfNeeded(array $data, ?BankAccount $except = null): void
    {
        if (! ($data['is_default'] ?? false)) {
            return;
        }

        BankAccount::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->update(['is_default' => false]);
    }
}
