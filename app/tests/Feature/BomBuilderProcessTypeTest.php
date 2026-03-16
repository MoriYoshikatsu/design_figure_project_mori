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
        $fiberCount = $processType === 'MFD' ? ($mfdCount + 1) : 1;

        $fibers = [];
        for ($i = 0; $i < $fiberCount; $i++) {
            $fibers[] = ['skuCode' => 'FIBER_A', 'lengthM' => 0.5, 'toleranceM' => 0.005];
        }

        $config = [
            'processType' => $processType,
            'mfdCount' => $mfdCount,
            'tubeCount' => 0,
            'sleeves' => $processType === 'MFD' ? [
                ['skuCode' => 'SLEEVE_RECOTE'],
                ['skuCode' => 'SLEEVE_RECOTE'],
                ['skuCode' => 'SLEEVE_RECOTE'],
            ] : [],
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

        $this->assertSame($expectedSku, (string)($processRow['sku_code'] ?? ''));
        $this->assertEquals($expectedQty, (int)($processRow['quantity'] ?? 0));
        $this->assertSame('$.processType', (string)($processRow['source_path'] ?? ''));

        if ($processType === 'MFD') {
            $options = is_array($processRow['options'] ?? null) ? $processRow['options'] : [];
            $this->assertSame($mfdCount, (int)($options['mfdCount'] ?? 0));
        }
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    public static function processTypeProvider(): array
    {
        return [
            ['MFD', 'PROC_MFD_CONVERSION', 3],
            ['TEC20', 'PROC_TEC20', 1],
            ['TEC30', 'PROC_TEC30', 1],
            ['TEC20_HP', 'PROC_TEC20_HP', 1],
            ['TEC30_HP', 'PROC_TEC30_HP', 1],
        ];
    }
}
