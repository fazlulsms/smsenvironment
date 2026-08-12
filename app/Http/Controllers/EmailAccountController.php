<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Support\CurrentEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailAccountController extends Controller
{
    public function __construct(private CurrentEntity $current) {}

    public function index(): View
    {
        return view('email_accounts.index', [
            'entity' => $this->current->get(),
            'accounts' => EmailAccount::query()
                ->where('business_entity_id', $this->current->id())
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('email_accounts.create', ['account' => new EmailAccount(['mailer_type' => 'smtp', 'port' => 587, 'encryption' => 'tls'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['business_entity_id'] = $this->current->id();
        $this->clearDefaultIfNeeded($data);

        EmailAccount::query()->create($data);

        return redirect()->route('email-accounts.index')->with('status', 'Email account saved.');
    }

    public function edit(EmailAccount $emailAccount): View
    {
        $this->authorizeEntity($emailAccount);

        return view('email_accounts.edit', ['account' => $emailAccount]);
    }

    public function update(Request $request, EmailAccount $emailAccount): RedirectResponse
    {
        $this->authorizeEntity($emailAccount);
        $data = $this->validated($request);

        // Password is write-only: keep the stored value when the field is blank.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $this->clearDefaultIfNeeded($data, $emailAccount);
        $emailAccount->update($data);

        return redirect()->route('email-accounts.index')->with('status', 'Email account updated.');
    }

    public function destroy(EmailAccount $emailAccount): RedirectResponse
    {
        $this->authorizeEntity($emailAccount);
        $emailAccount->delete();

        return redirect()->route('email-accounts.index')->with('status', 'Email account deleted.');
    }

    /** Reject email accounts belonging to another entity (route security). */
    private function authorizeEntity(EmailAccount $account): void
    {
        abort_unless($account->business_entity_id === $this->current->id(), 404);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'mailer_type' => ['required', 'string', 'in:smtp,sendmail,log'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'encryption' => ['nullable', 'in:tls,ssl,none'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['is_default'] = $request->boolean('is_default');
        if (($data['encryption'] ?? null) === 'none') {
            $data['encryption'] = null;
        }

        return $data;
    }

    private function clearDefaultIfNeeded(array $data, ?EmailAccount $except = null): void
    {
        if (! ($data['is_default'] ?? false)) {
            return;
        }

        EmailAccount::query()
            ->where('business_entity_id', $this->current->id())
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->update(['is_default' => false]);
    }
}
