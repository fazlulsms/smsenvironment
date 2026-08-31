<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_view_their_profile(): void
    {
        foreach ([User::ROLE_STAFF, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('profile.show'))->assertOk()->assertSee($user->email);
        }
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@smsea.test',
        ])->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@smsea.test', $user->email);
    }

    public function test_user_cannot_change_their_own_role_via_profile(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => User::ROLE_SUPER_ADMIN, // should be ignored — not fillable via profile
        ])->assertRedirect();

        $this->assertSame(User::ROLE_STAFF, $user->fresh()->role);
    }

    public function test_user_can_upload_replace_and_remove_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->staff()->create();

        // Upload
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
        ])->assertRedirect();

        $first = $user->fresh()->avatar_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        // Replace — old file removed, new file stored
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me2.png', 200, 200),
        ])->assertRedirect();

        $second = $user->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Remove
        $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertRedirect();
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_avatar_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('malware.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->staff()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('profile.show'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->staff()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_ensure_super_admin_command_promotes_primary_account(): void
    {
        $first = User::factory()->staff()->create();
        $second = User::factory()->staff()->create();

        // No email arg → earliest user is promoted.
        $this->artisan('smsea:ensure-super-admin')->assertSuccessful();

        $this->assertTrue($first->fresh()->isSuperAdmin());
        $this->assertTrue($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->isSuperAdmin());
    }

    public function test_ensure_super_admin_command_promotes_by_email(): void
    {
        User::factory()->staff()->create();
        $target = User::factory()->staff()->create(['email' => 'target@smsea.test']);

        $this->artisan('smsea:ensure-super-admin', ['email' => 'target@smsea.test'])->assertSuccessful();

        $this->assertTrue($target->fresh()->isSuperAdmin());
    }
}
