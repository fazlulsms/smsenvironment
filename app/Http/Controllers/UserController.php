<?php

namespace App\Http\Controllers;

use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Super Admin user management. Access is restricted to Super Admins by the
 * can:manage-users route middleware. The safety rules here (last-active-Super-Admin
 * protection, self-lockout prevention, deactivate-over-delete for users with
 * business records) are enforced in the controller because Super Admins bypass
 * policies via Gate::before — the guardrails must apply even to them.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->orderByDesc('is_active')->orderBy('name')->paginate(25),
            'roles' => User::ROLES,
            'activeSuperAdmins' => $this->activeSuperAdminCount(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['roles' => User::ROLES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => true,
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        Log::info('User created', ['user_id' => $user->id, 'role' => $user->role, 'by_user_id' => auth()->id()]);

        return redirect()->route('users.index')->with('status', "User {$user->name} created.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'roles' => User::ROLES,
            'isLastActiveSuperAdmin' => $this->isLastActiveSuperAdmin($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $downgradingFromSuperAdmin = $user->isSuperAdmin() && $data['role'] !== User::ROLE_SUPER_ADMIN;

        // Never leave the workspace without an active Super Admin.
        if ($downgradingFromSuperAdmin && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot downgrade the last active Super Admin. Promote another Super Admin first.',
            ]);
        }

        // Downgrading another Super Admin is a sensitive action — require an explicit confirm.
        if ($downgradingFromSuperAdmin && ! $request->boolean('confirm_downgrade')) {
            throw ValidationException::withMessages([
                'role' => 'Please confirm that you want to remove Super Admin access from this account.',
            ]);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        Log::info('User updated', ['user_id' => $user->id, 'role' => $user->role, 'by_user_id' => auth()->id()]);

        return redirect()->route('users.index')->with('status', "User {$user->name} updated.");
    }

    /** Activate / deactivate. Deactivation is preferred over deletion for real users. */
    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->is_active && $this->isLastActiveSuperAdmin($user)) {
            return back()->with('error', 'You cannot deactivate the last active Super Admin.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        Log::warning('User active state changed', [
            'user_id' => $user->id,
            'is_active' => $user->is_active,
            'by_user_id' => auth()->id(),
        ]);

        return back()->with('status', $user->is_active
            ? "User {$user->name} activated."
            : "User {$user->name} deactivated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        // Never delete yourself (avoids accidental self-lockout).
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Never delete the last active Super Admin.
        if ($user->isSuperAdmin() && $this->isLastActiveSuperAdmin($user)) {
            return back()->with('error', 'You cannot delete the last active Super Admin.');
        }

        // Prefer deactivation for users who have created real business records.
        $created = Quotation::withTrashed()->where('created_by', $user->id)->count()
            + ProformaInvoice::withTrashed()->where('created_by', $user->id)->count();

        if ($created > 0) {
            return back()->with('error', 'This user has created business documents and cannot be deleted. Deactivate the account instead to preserve history.');
        }

        $name = $user->name;
        $user->delete();

        Log::warning('User deleted', ['user_id' => $user->id, 'by_user_id' => auth()->id()]);

        return redirect()->route('users.index')->with('status', "User {$name} deleted.");
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('is_active', true)
            ->count();
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin()
            && $user->is_active
            && $this->activeSuperAdminCount() <= 1;
    }
}
