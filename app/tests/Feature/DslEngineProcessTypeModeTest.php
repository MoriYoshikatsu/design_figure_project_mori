<?php

namespace Tests\Feature;

use App\Services\DslEngine;
use Tests\TestCase;

final class DslEngineProcessTypeModeTest extends TestCase
{
    public function test_tec_mode_enforces_constraints(): void
    {
        $engine = app(DslEngine::class);

        $config = [
            'processType' => 'TEC20',
            'mfdCount' => 2,
            'tubeCount' => 2,
            'sleeves' => [
                ['skuCode' => 'SLEEVE_RECOTE'],
            ],
            'fibers' => [
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
            ],
            'tubes' => [],
            'connectors' => [
                'mode' => 'both',
                'leftSkuCode' => 'CONN_A',
                'rightSkuCode' => 'CONN_B',
            ],
        ];

        $result = $engine->evaluate($config, []);
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $paths = array_map(static fn(array $e): string => (string)($e['path'] ?? ''), $errors);

        $this->assertContains('fibers', $paths);
        $this->assertContains('tubeCount', $paths);
        $this->assertContains('sleeves', $paths);
        $this->assertContains('connectors.mode', $paths);
        $this->assertSame(1, (int)($result['derived']['fiberCount'] ?? 0));
    }

    public function test_mfd_mode_keeps_existing_count_rules(): void
    {
        $engine = app(DslEngine::class);

        $config = [
            'processType' => 'MFD',
            'mfdCount' => 2,
            'tubeCount' => 0,
            'sleeves' => [
                ['skuCode' => 'SLEEVE_RECOTE'],
                ['skuCode' => 'SLEEVE_RECOTE'],
            ],
            'fibers' => [
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
            ],
            'tubes' => [],
            'connectors' => [
                'mode' => 'both',
                'leftSkuCode' => 'CONN_A',
                'rightSkuCode' => 'CONN_B',
            ],
        ];

        $result = $engine->evaluate($config, []);
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $paths = array_map(static fn(array $e): string => (string)($e['path'] ?? ''), $errors);

        $this->assertSame(3, (int)($result['derived']['fiberCount'] ?? 0));
        $this->assertNotContains('fibers', $paths);
        $this->assertNotContains('tubeCount', $paths);
        $this->assertNotContains('connectors.mode', $paths);
    }
}
