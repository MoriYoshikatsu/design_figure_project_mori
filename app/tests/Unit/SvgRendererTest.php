<?php

namespace Tests\Unit;

use App\Services\SvgRenderer;
use PHPUnit\Framework\TestCase;

final class SvgRendererTest extends TestCase
{
    public function test_overall_dimension_reaches_svg_connector_outer_edges(): void
    {
        $renderer = new SvgRenderer();

        $svg = $renderer->render(
            [
                'processType' => 'TEC20',
                'tecSide' => 'left',
                'fibers' => [
                    ['lengthM' => 1.0],
                ],
                'tubes' => [],
                'connectors' => [
                    'mode' => 'both',
                    'leftSkuCode' => 'CONN-L',
                    'rightSkuCode' => 'CONN-R',
                ],
            ],
            [
                'fiberCount' => 1,
                'totalLengthM' => 1.0,
                'displaySegmentLens' => [1.0],
                'skuNameByCode' => [
                    'CONN-L' => 'Left Connector',
                    'CONN-R' => 'Right Connector',
                ],
                'skuSvgByCode' => [
                    'CONN-L' => '/sku-svg/CONN-L.svg',
                    'CONN-R' => '/sku-svg/CONN-R.svg',
                ],
            ]
        );

        $this->assertStringContainsString(
            '<line x1="20" y1="152" x2="980" y2="152" class="dim" />',
            $svg
        );
        $this->assertStringContainsString(
            '>1m</text>',
            $svg
        );
    }

    public function test_mfd_marker_is_drawn_at_fiber_seam(): void
    {
        $renderer = new SvgRenderer();

        $svg = $renderer->render(
            [
                'processType' => 'MFD',
                'sleeves' => [
                    ['skuCode' => 'SLEEVE-A'],
                ],
                'fibers' => [
                    ['lengthM' => 0.4],
                    ['lengthM' => 0.6],
                ],
                'tubes' => [],
                'connectors' => [
                    'mode' => 'none',
                    'leftSkuCode' => null,
                    'rightSkuCode' => null,
                ],
            ],
            [
                'fiberCount' => 2,
                'totalLengthM' => 1.0,
                'displaySegmentLens' => [0.4, 0.6],
                'skuNameByCode' => [
                    'SLEEVE-A' => 'Sleeve A',
                ],
                'skuSvgByCode' => [
                    'SLEEVE-A' => '/sku-svg/SLEEVE-A.svg',
                ],
            ]
        );

        $this->assertStringContainsString(
            '<line x1="416" y1="104" x2="416" y2="176" class="marker" id="mfd-0"',
            $svg
        );
        $this->assertStringContainsString(
            'MFD変換の数: 1 / ファイバーの数: 2',
            $svg
        );
    }

    public function test_spec_sheet_number_is_rendered_in_top_right(): void
    {
        $renderer = new SvgRenderer();

        $svg = $renderer->render(
            [
                'processType' => 'TEC20',
                'fibers' => [
                    ['lengthM' => 1.0],
                ],
                'tubes' => [],
                'connectors' => [
                    'mode' => 'none',
                    'leftSkuCode' => null,
                    'rightSkuCode' => null,
                ],
            ],
            [
                'fiberCount' => 1,
                'totalLengthM' => 1.0,
                'displaySegmentLens' => [1.0],
                'specSheetNumber' => 'SPEC-2026-001',
                'skuNameByCode' => [
                    'DUMMY' => 'Dummy',
                ],
                'skuSvgByCode' => [
                    'DUMMY' => '/sku-svg/DUMMY.svg',
                ],
            ]
        );

        $this->assertStringContainsString(
            '仕様書番号: SPEC-2026-001',
            $svg
        );
        $this->assertStringContainsString(
            'text-anchor="end"',
            $svg
        );
    }
}
