<?php

namespace Tests\Unit;

use App\Services\SkuDisplayNameService;
use PHPUnit\Framework\TestCase;

final class SkuDisplayNameServiceTest extends TestCase
{
    public function test_it_prefers_name_en_for_english_ui(): void
    {
        $service = new SkuDisplayNameService();

        $label = $service->resolveDisplayName('FIBER_A', '光ファイバA', 'Fiber A', 'en');

        $this->assertSame('Fiber A', $label);
    }

    public function test_it_falls_back_to_part_code_when_only_japanese_name_exists_for_english_ui(): void
    {
        $service = new SkuDisplayNameService();

        $label = $service->resolveDisplayName('TUBE_09', 'チューブ0.9', null, 'en');

        $this->assertSame('TUBE_09', $label);
    }

    public function test_it_keeps_primary_name_for_japanese_ui(): void
    {
        $service = new SkuDisplayNameService();

        $label = $service->resolveDisplayName('CONN_SC', 'コネクタSC', 'SC Connector', 'ja');

        $this->assertSame('コネクタSC', $label);
    }
}
