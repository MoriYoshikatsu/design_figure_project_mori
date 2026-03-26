<?php

namespace Tests\Feature;

use App\Livewire\Configurator;
use ReflectionClass;
use Tests\TestCase;

final class ConfiguratorMfdAdjustmentsAggregationTest extends TestCase
{
    public function test_ratio_rows_are_aggregated_into_mfd_process_override(): void
    {
        $component = new Configurator();
        $component->quoteEditId = 1;
        $component->config = [
            'processType' => 'MFD',
            'mfdAdjustments' => [
                ['yield_rate' => null, 'order_qty' => 10, 'actual_input_qty' => 20],
                ['yield_rate' => null, 'order_qty' => 5, 'actual_input_qty' => 10],
                ['yield_rate' => 0.9, 'order_qty' => null, 'actual_input_qty' => null],
            ],
        ];
        $component->laborOverrides = [
            'processes' => [
                'MFD' => ['yield_rate' => 0.7],
            ],
            'elements' => [],
        ];

        $result = $this->invokeBuildEffectiveLaborOverrides($component);
        $mfd = $result['processes']['MFD'] ?? [];

        $this->assertEqualsWithDelta(15.0, (float)($mfd['order_qty'] ?? 0), 0.000001);
        $this->assertEqualsWithDelta(30.0, (float)($mfd['actual_input_qty'] ?? 0), 0.000001);
        $this->assertEqualsWithDelta(0.5, (float)($mfd['yield_rate'] ?? 0), 0.000001);
    }

    public function test_yield_only_rows_use_average_when_ratio_missing(): void
    {
        $component = new Configurator();
        $component->quoteEditId = 1;
        $component->config = [
            'processType' => 'MFD',
            'mfdAdjustments' => [
                ['yield_rate' => 0.8, 'order_qty' => null, 'actual_input_qty' => null],
                ['yield_rate' => 0.9, 'order_qty' => null, 'actual_input_qty' => null],
            ],
        ];
        $component->laborOverrides = [
            'processes' => [],
            'elements' => [],
        ];

        $result = $this->invokeBuildEffectiveLaborOverrides($component);
        $mfd = $result['processes']['MFD'] ?? [];

        $this->assertEqualsWithDelta(0.85, (float)($mfd['yield_rate'] ?? 0), 0.000001);
        $this->assertNull($mfd['order_qty'] ?? null);
        $this->assertNull($mfd['actual_input_qty'] ?? null);
    }

    public function test_configurator_mode_does_not_apply_mfd_adjustments(): void
    {
        $component = new Configurator();
        $component->quoteEditId = null;
        $component->config = [
            'processType' => 'MFD',
            'mfdAdjustments' => [
                ['yield_rate' => null, 'order_qty' => 10, 'actual_input_qty' => 20],
            ],
        ];
        $component->laborOverrides = [
            'processes' => [],
            'elements' => [],
        ];

        $result = $this->invokeBuildEffectiveLaborOverrides($component);
        $this->assertArrayNotHasKey('MFD', $result['processes'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeBuildEffectiveLaborOverrides(Configurator $component): array
    {
        $ref = new ReflectionClass($component);
        $method = $ref->getMethod('buildEffectiveLaborOverrides');
        $method->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $method->invoke($component);
        return $result;
    }
}
