<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('parts')->get(['id', 'part_code', 'category', 'attributes']);
        foreach ($rows as $row) {
            $attributes = $this->decodeAttributes($row->attributes);
            $originalTags = $this->normalizeTags($attributes['process_tags'] ?? []);
            $nextTags = $originalTags;

            $skuCode = strtoupper(trim((string)$row->part_code));
            $category = strtoupper(trim((string)$row->category));
            $polish = strtoupper(trim((string)($attributes['polish'] ?? '')));

            if ($skuCode === 'PROC_MFD') {
                $nextTags[] = 'mfd';
            }
            if ($skuCode === 'FIBER_PMF') {
                $nextTags[] = 'pm';
            }
            if ($category === 'CONNECTOR') {
                $nextTags[] = 'connector';
            }
            if ($polish === 'APC' || str_contains($skuCode, '_APC')) {
                $nextTags[] = 'apc';
            }

            $nextTags = $this->normalizeTags($nextTags);
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
        // no-op: this migration intentionally preserves user-updated tags
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
};

