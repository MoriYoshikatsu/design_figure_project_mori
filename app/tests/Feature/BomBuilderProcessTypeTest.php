<?php

namespace Tests\Feature;

use App\Services\BomBuilder;
use Tests\TestCase;

final class BomBuilderProcessTypeTest extends TestCase
{
    /**
     * @dataProvider processTypeProvider
     */
    public function test_build_default_adds_expected_process_item(string $processType, string $expectedSku, int $expectedQty): void
    {
        $builder = app(BomBuilder::class);

        $mfdCount = 3;
        $fiberCount = $processType === 'MFD' ? 2 : 1;

        $fibers = [];
        for ($i = 0; $i < $fiberCount; $i++) {
            $fibers[] = ['skuCode' => 'FIBER_A', 'lengthM' => 0.5, 'toleranceM' => 0.005];
        }

        $config = [
            'processType' => $processType,
            'mfdCount' => $mfdCount,
            'tubeCount' => 0,
            'tecSide' => 'left',
            'sleeves' => $processType === 'MFD' ? [['skuCode' => 'SLEEVE_RECOTE']] : [],
            'fibers' => $fibers,
            'tubes' => [],
            'connectors' => [
                'mode' => 'none',
                'leftSkuCode' => null,
                'rightSkuCode' => null,
            ],
        ];

        $bom = $builder->build($config, [], []);
        $processRow = $bom[0] ?? [];

        $this->assertSame($expectedSku, (string)($processRow['part_code'] ?? ''));
        $this->assertEquals($expectedQty, (int)($processRow['quantity'] ?? 0));
        $this->assertSame('$.processType', (string)($processRow['source_path'] ?? ''));

        if ($processType === 'MFD') {
            $options = is_array($processRow['options'] ?? null) ? $processRow['options'] : [];
            $this->assertSame(1, (int)($options['mfdCount'] ?? 0));
            $this->assertEqualsWithDelta(1.0, (float)($options['totalFiberLengthM'] ?? 0.0), 0.000001);
            $this->assertCount(2, is_array($options['fiberItems'] ?? null) ? $options['fiberItems'] : []);
        } else {
            $options = is_array($processRow['options'] ?? null) ? $processRow['options'] : [];
            $this->assertSame('left', (string)($options['tecSide'] ?? ''));
        }
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    public static function processTypeProvider(): array
    {
        return [
            ['MFD', 'PROC_MFD_CONVERSION', 1],
            ['TEC20', 'PROC_TEC20', 1],
            ['TEC30', 'PROC_TEC30', 1],
            ['TEC20_HP', 'PROC_TEC20_HP', 1],
            ['TEC30_HP', 'PROC_TEC30_HP', 1],
        ];
    }

    public function test_build_default_adds_dual_tec_process_items_for_both_ends(): void
    {
        $builder = app(BomBuilder::class);

        $config = [
            'processType' => 'TEC',
            'mfdCount' => 1,
            'tubeCount' => 0,
            'tecSide' => 'both',
            'tecLeftProcessType' => 'TEC20',
            'tecRightProcessType' => 'TEC30_HP',
            'sleeves' => [],
            'fibers' => [
                ['skuCode' => 'FIBER_A', 'lengthM' => 0.5, 'toleranceM' => 0.005],
            ],
            'tubes' => [],
            'connectors' => [
                'mode' => 'none',
                'leftSkuCode' => null,
                'rightSkuCode' => null,
            ],
        ];

        $bom = $builder->build($config, [], []);

        $this->assertSame('PROC_TEC20', (string)($bom[0]['part_code'] ?? ''));
        $this->assertSame('$.tecLeftProcessType', (string)($bom[0]['source_path'] ?? ''));
        $this->assertSame('left', (string)($bom[0]['options']['tecSide'] ?? ''));

        $this->assertSame('PROC_TEC30_HP', (string)($bom[1]['part_code'] ?? ''));
        $this->assertSame('$.tecRightProcessType', (string)($bom[1]['source_path'] ?? ''));
        $this->assertSame('right', (string)($bom[1]['options']['tecSide'] ?? ''));
    }
}
