<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Standard;
use App\Models\User;
use App\Support\ServiceCatalogue;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seed(StandardSeeder::class);
        $this->seed(ServiceSeeder::class); // legacy services too
    }

    public function test_catalogue_unifies_standards_and_legacy_services_without_duplicates(): void
    {
        $all = ServiceCatalogue::all();

        // Standards (168) + legacy-only services, deduped by name.
        $this->assertGreaterThan(Standard::query()->count(), $all->count());

        // A name shared by both tables (Energy Audit) appears exactly once (standard is canonical).
        $this->assertSame(1, $all->filter(fn ($i) => strcasecmp($i['name'], 'Energy Audit') === 0)->count());
    }

    public function test_services_page_shows_the_full_catalogue(): void
    {
        $response = $this->actingAs($this->user)->get(route('services.index'))->assertOk();

        // The screen is the catalogue, not just the legacy table.
        foreach (['ISO 9001', 'ISO 14001', 'ISO 45001', 'GOTS', 'GRS', 'amfori BSCI Audit',
            'SLCP Assessment', 'Higg FEM Verification', 'Energy Audit', 'Environmental Parameter Testing'] as $needle) {
            // Search each so pagination never hides a match.
            $this->actingAs($this->user)->get(route('services.index', ['search' => $needle]))
                ->assertOk()->assertSee($needle, false);
        }

        $response->assertSee('Service Catalogue');
        $response->assertSee('New Catalogue Item');
    }

    public function test_search_by_code_and_by_full_name(): void
    {
        $this->actingAs($this->user)->get(route('services.index', ['search' => '9001']))
            ->assertOk()->assertSee('ISO 9001', false);
        $this->actingAs($this->user)->get(route('services.index', ['search' => 'Quality Management Systems']))
            ->assertOk()->assertSee('ISO 9001', false);
        $this->actingAs($this->user)->get(route('services.index', ['search' => 'BSCI']))
            ->assertOk()->assertSee('amfori BSCI Audit', false);
    }

    public function test_category_and_type_filters(): void
    {
        $iso = ServiceCategory::query()->where('code', 'ISO_MGMT')->firstOrFail();

        // Category filter → only that category's items.
        $filtered = ServiceCatalogue::filter(ServiceCatalogue::all(), ['category' => 'ISO_MGMT']);
        $this->assertTrue($filtered->every(fn ($i) => $i['category_code'] === 'ISO_MGMT'));
        $this->assertSame($iso->activeStandards()->count(), $filtered->count());

        // Type filter → only packages (with component counts).
        $packages = ServiceCatalogue::filter(ServiceCatalogue::all(), ['type' => 'Package']);
        $this->assertTrue($packages->isNotEmpty());
        $this->assertTrue($packages->every(fn ($i) => $i['type'] === 'Package'));
        $ept = $packages->firstWhere('name', 'Environmental Parameter Testing');
        $this->assertSame(7, $ept['components']);
    }

    public function test_category_counts_come_from_the_database(): void
    {
        $counts = ServiceCatalogue::categoryCounts(ServiceCatalogue::all());
        $iso = $counts->firstWhere('code', 'ISO_MGMT');
        $this->assertSame(14, $iso['count']); // 14 ISO standards seeded
    }

    public function test_catalogue_item_edit_and_update(): void
    {
        $iso = Standard::query()->where('code', 'ISO 9001')->firstOrFail();

        $this->actingAs($this->user)->get(route('catalogue-standards.edit', $iso))
            ->assertOk()->assertSee('ISO 9001', false);

        $this->actingAs($this->user)->put(route('catalogue-standards.update', $iso), [
            'service_category_id' => $iso->service_category_id,
            'name' => $iso->name, 'short_name' => 'ISO 9001', 'code' => 'ISO 9001',
            'type' => 'ISO Standard', 'active' => '1', 'display_order' => 1,
            'default_scope' => '',
        ])->assertRedirect(route('catalogue-standards.edit', $iso));

        $this->assertSame('ISO 9001', $iso->fresh()->short_name);
    }

    public function test_creating_a_catalogue_item_does_not_duplicate_and_keeps_slug_stable(): void
    {
        $cat = ServiceCategory::query()->where('code', 'ENVIRO_SUSTAIN')->firstOrFail();
        $before = Standard::query()->count();

        $this->actingAs($this->user)->post(route('catalogue-standards.store'), [
            'service_category_id' => $cat->id, 'name' => 'Custom Water Package', 'code' => null,
            'type' => 'Environmental Service', 'active' => '1',
            'default_scope' => "pH\nBOD\nCOD",
        ])->assertRedirect();

        $this->assertSame($before + 1, Standard::query()->count());
        $created = Standard::query()->where('name', 'Custom Water Package')->firstOrFail();
        $this->assertSame('custom-water-package', $created->slug);
        $this->assertCount(3, $created->defaultScope());
    }

    public function test_seeders_remain_idempotent_after_reseed(): void
    {
        $standards = Standard::query()->count();
        $services = Service::query()->count();

        $this->seed(StandardSeeder::class);
        $this->seed(ServiceSeeder::class);

        $this->assertSame($standards, Standard::query()->count());
        $this->assertSame($services, Service::query()->count());
    }
}
