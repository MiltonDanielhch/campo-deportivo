<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_responde_operational(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'operational')
            ->assertJsonStructure([
                'status',
                'service',
                'version',
                'timezone',
                'timestamp',
            ]);
    }
}
