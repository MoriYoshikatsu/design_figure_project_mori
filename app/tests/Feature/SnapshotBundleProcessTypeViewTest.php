<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SnapshotBundleProcessTypeViewTest extends TestCase
{
    public function test_snapshot_bundle_renders_single_tec_process_row(): void
    {
        $html = view('snapshot_bundle', [
            'svg' => '<svg></svg>',
            'config' => [
                'processType' => 'TEC20',
                'mfdCount' => 3,
                'tubeCount' => 1,
                'sleeves' => [],
                'fibers' => [
                    ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
                ],
                'tubes' => [
                    ['skuCode' => 'TUBE_A', 'startFiberIndex' => 0, 'endFiberIndex' => 0, 'startOffsetM' => 0, 'endOffsetM' => 0.2],
                ],
                'connectors' => ['mode' => 'left', 'leftSkuCode' => 'CONN_A', 'rightSkuCode' => null],
            ],
            'derived' => [
                'skuNameByCode' => [
                    'PROC_TEC20' => 'TEC20加工',
                    'FIBER_A' => 'Fiber A',
                    'TUBE_A' => 'Tube A',
                    'CONN_A' => 'Conn A',
                ],
            ],
            'errors' => [],
            'snapshot' => [
                'template_version_id' => 1,
                'price_book_id' => 1,
                'totals' => ['subtotal' => 1000, 'tax' => 100, 'total' => 1100],
                'bom' => [
                    ['sku_code' => 'PROC_TEC20', 'quantity' => 1, 'source_path' => '$.processType', 'sort_order' => 0],
                ],
                'pricing' => [
                    ['sort_order' => 0, 'unit_price' => 500, 'line_total' => 500],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('工程種別', $html);
        $this->assertStringContainsString('TEC20', $html);
        $this->assertStringContainsString('TEC工程', $html);
        $this->assertStringNotContainsString('スリーブ(MFD)', $html);
    }

    public function test_snapshot_bundle_keeps_mfd_row_expansion(): void
    {
        $html = view('snapshot_bundle', [
            'svg' => '<svg></svg>',
            'config' => [
                'processType' => 'MFD',
                'mfdCount' => 2,
                'tubeCount' => 0,
                'sleeves' => [
                    ['skuCode' => 'SLEEVE_A'],
                    ['skuCode' => 'SLEEVE_B'],
                ],
                'fibers' => [
                    ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
                    ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
                    ['skuCode' => 'FIBER_A', 'lengthM' => 0.5],
                ],
                'tubes' => [],
                'connectors' => ['mode' => 'none', 'leftSkuCode' => null, 'rightSkuCode' => null],
            ],
            'derived' => [
                'skuNameByCode' => [
                    'PROC_MFD_CONVERSION' => 'MFD変換(構成)',
                    'SLEEVE_A' => 'Sleeve A',
                    'SLEEVE_B' => 'Sleeve B',
                    'FIBER_A' => 'Fiber A',
                ],
            ],
            'errors' => [],
            'snapshot' => [
                'template_version_id' => 1,
                'price_book_id' => 1,
                'totals' => ['subtotal' => 1000, 'tax' => 100, 'total' => 1100],
                'bom' => [
                    ['sku_code' => 'PROC_MFD_CONVERSION', 'quantity' => 2, 'source_path' => '$.processType', 'sort_order' => 0],
                ],
                'pricing' => [
                    ['sort_order' => 0, 'unit_price' => 2000, 'line_total' => 4000],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('工程種別', $html);
        $this->assertStringContainsString('MFD', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'MFD変換'));
    }
}
