<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_is_the_public_website(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Environmental, Chemical &amp; Sustainability Solutions for Responsible Industry', false);
    }

    public function test_office_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_office_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Quick actions')
            ->assertSee('Quotations');
    }
}
