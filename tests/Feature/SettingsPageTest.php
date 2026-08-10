<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_with_literal_email_template_placeholders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.edit'));

        $response->assertOk();
        // The email-template hints must appear literally, not be evaluated by Blade.
        $response->assertSee('Quotation for {{service_name}} - {{client_name}}', false);
        $response->assertSee('Proforma Invoice for {{service_name}} - {{client_name}}', false);
    }
}
