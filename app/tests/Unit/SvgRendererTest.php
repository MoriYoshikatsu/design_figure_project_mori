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
                    ['lengthM' => 1.0, 'toleranceM' => 0.1],
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
            '<line x1="20" y1="164" x2="980" y2="164" class="dim" />',
            $svg
        );
        $this->assertStringContainsString(
            '>1m ± 0.1m</text>',
            $svg
        );
        $this->assertStringContainsString(
            '<text x="80" y="18" class="label">',
            $svg
        );
    }

    public function test_tec_marker_leaves_gap_around_fiber(): void
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
            '<line x1="20" y1="110" x2="20" y2="132.5" stroke="#2563eb" stroke-width="2" stroke-dasharray="3 3" />',
            $svg
        );
        $this->assertStringNotContainsString(
            '<line x1="20" y1="147.5" x2="20" y2="170" stroke="#2563eb" stroke-width="2" stroke-dasharray="3 3" />',
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
            '<line x1="416" y1="104" x2="416" y2="132.5" class="marker" id="mfd-0" data-path="mfd.0" stroke="#9ca3af" stroke-dasharray="4 4" opacity="0.7" />',
            $svg
        );
        $this->assertStringNotContainsString(
            '<line x1="416" y1="147.5" x2="416" y2="176" class="marker" stroke="#9ca3af" stroke-dasharray="4 4" opacity="0.7" />',
            $svg
        );
        $this->assertStringContainsString(
            'MFD変換の数: 1 / ファイバーの数: 2',
            $svg
        );
    }

    public function test_recoat_sleeve_is_rendered_as_orange_rectangle(): void
    {
        $renderer = new SvgRenderer();

        $svg = $renderer->render(
            [
                'processType' => 'MFD',
                'sleeves' => [
                    ['skuCode' => 'SLEEVE_RECOTE'],
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
                    'SLEEVE_RECOTE' => 'Recoat Sleeve',
                ],
                'skuSvgByCode' => [
                    'SLEEVE_RECOTE' => '/sku-svg/SLEEVE_RECOTE.svg',
                ],
            ]
        );

        $this->assertStringContainsString(
            '.recoat { fill:#f97316; stroke:#c2410c; stroke-width:1.25; }',
            $svg
        );
        $this->assertStringContainsString(
            '<rect x="395" y="136" width="42" height="8" rx="2" class="recoat" id="sleeve-0" data-path="sleeves.0" />',
            $svg
        );
        $this->assertStringNotContainsString(
            '<image href="/sku-svg/SLEEVE_RECOTE.svg"',
            $svg
        );
    }

    public function test_tube_is_rendered_with_thinner_profile(): void
    {
        $renderer = new SvgRenderer();

        $svg = $renderer->render(
            [
                'processType' => 'TEC20',
                'fibers' => [
                    ['lengthM' => 1.0],
                ],
                'tubes' => [
                    ['skuCode' => null, 'targetFiberIndex' => 0, 'lengthM' => 1.0],
                ],
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
                'skuSvgByCode' => [
                    'DUMMY' => '/sku-svg/DUMMY.svg',
                ],
            ]
        );

        $this->assertStringContainsString(
            '.tube { fill:none; stroke:#facc15; stroke-width:1.25; opacity:0.95; }',
            $svg
        );
        $this->assertStringContainsString(
            '<rect x="80" y="138.95" width="840" height="2.1" class="tube" id="tube-0" data-path="tubes.0" />',
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
            '<text x="920" y="18" class="small" text-anchor="end">',
            $svg
        );
        $this->assertStringContainsString(
            'text-anchor="end"',
            $svg
        );
    }
}
