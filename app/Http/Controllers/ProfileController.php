<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Self-service profile for the signed-in user. Anyone authenticated may view and
 * edit their own name, email, avatar and password. Roles and account status are
 * intentionally NOT editable here — only a Super Admin can change those, via User
 * management.
 */
class ProfileController extends Controller
{
    public function show(): View
    {
        return view('profile.show', ['user' => Auth::user()]);
    }

    /**
     * Stream a user's avatar straight from the public disk. Used instead of a
     * /storage symlink so avatars work on any host without a symlink and without
     * depending on APP_URL. Any authenticated Office user may view avatars.
     */
    public function avatar(User $user): StreamedResponse
    {
        abort_unless($user->avatar_path && Storage::disk('public')->exists($user->avatar_path), 404);

        return Storage::disk('public')->response($user->avatar_path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];

        if ($request->hasFile('avatar')) {
            // Replace: remove the previous file so we don't accumulate orphans.
            $this->deleteAvatarFile($user->avatar_path);
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        return redirect()->route('profile.show')->with('status', 'Profile updated.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            $this->deleteAvatarFile($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return redirect()->route('profile.show')->with('status', 'Profile photo removed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return redirect()->route('profile.show')->with('status', 'Password changed.');
    }

    private function deleteAvatarFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
