<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_uploading_a_logo_syncs_the_owning_entity(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $settings = Setting::current();
        $entity = BusinessEntity::query()->findOrFail($settings->business_entity_id);

        // Submit the current settings (satisfying all required fields) plus a new logo.
        $payload = collect($settings->toArray())
            ->except(['id', 'business_entity_id', 'created_at', 'updated_at'])
            ->map(fn ($v) => $v ?? '')
            ->all();
        $payload['logo'] = UploadedFile::fake()->image('brand.png', 240, 240);

        $this->actingAs($user)->put(route('settings.update'), $payload)
            ->assertRedirect(route('settings.edit'));

        $settings->refresh();
        $entity->refresh();

        $this->assertNotNull($settings->logo_path);
        $this->assertSame($settings->logo_path, $entity->logo_path, 'entity logo must mirror the uploaded settings logo');
        Storage::disk('public')->assertExists($settings->logo_path);
    }
}
