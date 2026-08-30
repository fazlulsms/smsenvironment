<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Go-live entity launch-safety command. */
class GoLiveCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_launch_only_deactivates_secondary_entities_and_keeps_smsea(): void
    {
        $this->artisan('smsea:launch-only')->assertExitCode(0);

        $this->assertTrue((bool) BusinessEntity::query()->where('entity_code', 'SMSEA')->value('active'));
        $this->assertTrue((bool) BusinessEntity::query()->where('entity_code', 'SMSEA')->value('is_default'));

        foreach (['EIDIKOS', 'ECOVERITAS', 'MAXINT', 'ICQMS'] as $code) {
            $this->assertFalse((bool) BusinessEntity::query()->where('entity_code', $code)->value('active'), "$code should be inactive");
        }
    }

    public function test_launch_only_reactivate_restores_all_entities(): void
    {
        $this->artisan('smsea:launch-only')->assertExitCode(0);
        $this->artisan('smsea:launch-only', ['--reactivate' => true])->assertExitCode(0);

        foreach (['SMSEA', 'EIDIKOS', 'ECOVERITAS', 'MAXINT', 'ICQMS'] as $code) {
            $this->assertTrue((bool) BusinessEntity::query()->where('entity_code', $code)->value('active'), "$code should be active");
        }
        // SMSEA stays the default.
        $this->assertTrue((bool) BusinessEntity::query()->where('entity_code', 'SMSEA')->value('is_default'));
    }
}
