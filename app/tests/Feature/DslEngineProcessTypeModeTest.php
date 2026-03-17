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
            'mfdCount' => 9,
            'tubeCount' => 3,
            'tecSide' => null,
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
        $this->assertContains('tecSide', $paths);
        $this->assertContains('sleeves', $paths);
        $this->assertNotContains('connectors.mode', $paths);
        $this->assertSame(1, (int)($result['derived']['fiberCount'] ?? 0));
    }

    public function test_mfd_mode_uses_fixed_single_fiber_and_allows_two_tubes(): void
    {
        $engine = app(DslEngine::class);

        $config = [
            'processType' => 'MFD',
            'mfdCount' => 8,
            'tubeCount' => 2,
            'sleeves' => [
                ['skuCode' => 'SLEEVE_RECOTE'],
            ],
            'fibers' => [
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
            ],
            'tubes' => [
                ['skuCode' => 'TUBE_A', 'startFiberIndex' => 0, 'endFiberIndex' => 0, 'startOffsetM' => 0.0, 'endOffsetM' => 0.2],
                ['skuCode' => 'TUBE_A', 'startFiberIndex' => 0, 'endFiberIndex' => 0, 'startOffsetM' => 0.1, 'endOffsetM' => 0.4],
            ],
            'connectors' => [
                'mode' => 'both',
                'leftSkuCode' => 'CONN_A',
                'rightSkuCode' => 'CONN_B',
            ],
        ];

        $result = $engine->evaluate($config, []);
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $paths = array_map(static fn(array $e): string => (string)($e['path'] ?? ''), $errors);

        $this->assertSame(1, (int)($result['derived']['fiberCount'] ?? 0));
        $this->assertNotContains('fibers', $paths);
        $this->assertNotContains('tubeCount', $paths);
        $this->assertNotContains('connectors.mode', $paths);
    }

    public function test_length_ranges_are_validated(): void
    {
        $engine = app(DslEngine::class);

        $config = [
            'processType' => 'TEC30',
            'mfdCount' => 1,
            'tubeCount' => 1,
            'tecSide' => 'left',
            'sleeves' => [],
            'fibers' => [
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.1],
            ],
            'tubes' => [
                ['skuCode' => 'TUBE_A', 'startFiberIndex' => 0, 'endFiberIndex' => 0, 'startOffsetM' => 0.0, 'endOffsetM' => 2.1],
            ],
            'connectors' => [
                'mode' => 'both',
                'leftSkuCode' => 'CONN_A',
                'rightSkuCode' => 'CONN_B',
            ],
        ];

        $result = $engine->evaluate($config, []);
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $paths = array_map(static fn(array $e): string => (string)($e['path'] ?? ''), $errors);

        $this->assertContains('fibers.0.lengthM', $paths);
        $this->assertContains('tubes.0.endOffsetM', $paths);
    }
}
