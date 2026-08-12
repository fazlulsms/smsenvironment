<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityThemeTest extends TestCase
{
    use RefreshDatabase;

    private function entity(string $code): BusinessEntity
    {
        return BusinessEntity::query()->where('entity_code', $code)->firstOrFail();
    }

    public function test_active_entity_theme_colors_render_in_the_layout(): void
    {
        $user = User::factory()->create();
        $eco = $this->entity('ECOVERITAS');
        $icqms = $this->entity('ICQMS');
        $this->actingAs($user);

        $this->post(route('entities.switch'), ['entity_id' => $eco->id]);
        $ecoHtml = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('--entity-primary:'.$eco->primary_color, $ecoHtml);

        $this->post(route('entities.switch'), ['entity_id' => $icqms->id]);
        $icqmsHtml = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('--entity-primary:'.$icqms->primary_color, $icqmsHtml);
        $this->assertStringNotContainsString('--entity-primary:'.$eco->primary_color, $icqmsHtml);
    }

    public function test_every_seeded_entity_has_a_distinct_theme(): void
    {
        $primaries = BusinessEntity::query()->pluck('primary_color')->filter();
        $this->assertSame($primaries->count(), $primaries->unique()->count());
        $this->assertSame('#1f6f4a', $this->entity('SMSEA')->primary_color); // SMSEA keeps its green
    }

    public function test_entity_management_updates_theme_but_not_entity_code(): void
    {
        $user = User::factory()->create();
        $eco = $this->entity('ECOVERITAS');

        $this->actingAs($user)->get(route('entities.index'))->assertOk();
        $this->actingAs($user)->get(route('entities.edit', $eco))->assertOk();

        $this->actingAs($user)->put(route('entities.update', $eco), [
            'name' => 'EcoVeritas International',
            'default_currency' => 'USD',
            'primary_color' => '#123456',
            'entity_code' => 'HACKED',
        ])->assertRedirect();

        $eco->refresh();
        $this->assertSame('#123456', $eco->primary_color);
        $this->assertSame('USD', $eco->default_currency);
        $this->assertSame('ECOVERITAS', $eco->entity_code); // locked
    }
}
