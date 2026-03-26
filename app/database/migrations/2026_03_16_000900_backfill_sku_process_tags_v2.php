<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parts')) {
            return;
        }

        $rows = DB::table('parts')->get(['id', 'part_code', 'category', 'attributes']);
        foreach ($rows as $row) {
            $attributes = $this->decodeAttributes($row->attributes);
            $originalTags = $this->normalizeTags($attributes['process_tags'] ?? []);

            $skuCode = strtoupper(trim((string)$row->part_code));
            $category = strtoupper(trim((string)$row->category));
            $inferred = $this->inferTagsForSku($skuCode, $category, $attributes);
            $nextTags = $this->normalizeTags(array_merge($originalTags, $inferred));
            if ($nextTags === $originalTags) {
                continue;
            }

            $attributes['process_tags'] = $nextTags;
            DB::table('parts')
                ->where('id', (int)$row->id)
                ->update([
                    'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // no-op: retain inferred tags
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAttributes(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, mixed>|mixed $tags
     * @return array<int, string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            $value = strtolower(trim((string)$tag));
            if ($value === '') {
                continue;
            }
            $normalized[$value] = true;
        }

        $result = array_keys($normalized);
        sort($result);
        return $result;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<int, string>
     */
    private function inferTagsForSku(string $skuCode, string $category, array $attributes): array
    {
        $code = strtoupper(trim($skuCode));
        $cat = strtoupper(trim($category));
        $kind = strtolower(trim((string)($attributes['kind'] ?? '')));
        $polish = strtoupper(trim((string)($attributes['polish'] ?? '')));
        $tags = [];

        if ($code !== '') {
            $tags[strtolower($code)] = true;
        }
        if ($cat !== '') {
            $tags[strtolower($cat)] = true;
        }
        if ($cat === 'CONNECTOR' || str_starts_with($code, 'CONN_')) {
            $tags['connector'] = true;
        }
        if ($cat === 'FIBER' || str_starts_with($code, 'FIBER_')) {
            $tags['fiber'] = true;
        }
        if ($cat === 'TUBE' || str_starts_with($code, 'TUBE_')) {
            $tags['tube'] = true;
        }
        if ($cat === 'SLEEVE' || str_starts_with($code, 'SLEEVE_')) {
            $tags['sleeve'] = true;
            $tags['fusion'] = true;
        }

        if (str_contains($code, 'MFD') || str_contains($kind, 'mfd')) {
            $tags['mfd'] = true;
        }
        if (str_contains($code, 'TEC20') || str_contains($kind, 'tec20')) {
            $tags['tec20'] = true;
        }
        if (str_contains($code, 'TEC30') || str_contains($kind, 'tec30')) {
            $tags['tec30'] = true;
        }
        if (str_contains($code, '_HP') || str_contains($kind, 'high_precision') || str_contains($kind, '_hp')) {
            $tags['high_precision'] = true;
        }

        if (str_contains($code, 'PM') || str_contains($kind, 'pm')) {
            $tags['pm'] = true;
        }
        if ($polish === 'APC' || str_contains($code, '_APC')) {
            $tags['apc'] = true;
        }
        if ($polish === 'ARCOAT' || str_contains($code, 'ARCOAT')) {
            $tags['arcoat'] = true;
        }
        if ($polish === 'PC' || str_contains($code, '_PC')) {
            $tags['pc'] = true;
        }

        if (str_starts_with($code, 'CONN_SC_')) {
            $tags['conn_sc'] = true;
        } elseif (str_starts_with($code, 'CONN_FC_')) {
            $tags['conn_fc'] = true;
        } elseif (str_starts_with($code, 'CONN_LC_')) {
            $tags['conn_lc'] = true;
        } elseif (str_starts_with($code, 'CONN_FERRULE_')) {
            $tags['conn_ferrule'] = true;
        }

        $result = array_keys($tags);
        sort($result);
        return $result;
    }
};
