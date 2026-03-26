<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SkuDisplayNameService
{
    private ?bool $hasNameEnColumn = null;
    private ?bool $hasDeletedAtColumn = null;

    /**
     * @return array<string, string>
     */
    public function buildNameMap(string $uiLanguage = 'ja', bool $activeOnly = false): array
    {
        try {
            $query = DB::table('parts');
            if ($activeOnly) {
                $query->where('active', true);
            }
            if ($this->hasDeletedAtColumn()) {
                $query->whereNull('deleted_at');
            }

            $columns = ['part_code', 'name'];
            if ($this->hasNameEnColumn()) {
                $columns[] = 'name_en';
            }

            $rows = $query->get($columns);

            $map = [];
            foreach ($rows as $row) {
                $code = trim((string)($row->part_code ?? ''));
                if ($code === '') {
                    continue;
                }
                $map[$code] = $this->resolveDisplayName(
                    $code,
                    $row->name ?? null,
                    $row->name_en ?? null,
                    $uiLanguage
                );
            }

            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{
     *   sleeve: array<int, array{code:string,label:string}>,
     *   fiber: array<int, array{code:string,label:string}>,
     *   tube: array<int, array{code:string,label:string}>,
     *   connector: array<int, array{code:string,label:string}>
     * }
     */
    public function buildOptionsByCategory(string $uiLanguage = 'ja', bool $activeOnly = true): array
    {
        $byCategory = [
            'SLEEVE' => [],
            'FIBER' => [],
            'TUBE' => [],
            'CONNECTOR' => [],
        ];

        try {
            $query = DB::table('parts');
            if ($activeOnly) {
                $query->where('active', true);
            }
            if ($this->hasDeletedAtColumn()) {
                $query->whereNull('deleted_at');
            }

            $columns = ['part_code', 'name', 'category'];
            if ($this->hasNameEnColumn()) {
                $columns[] = 'name_en';
            }

            $rows = $query
                ->orderBy('category')
                ->orderBy('part_code')
                ->get($columns);

            foreach ($rows as $row) {
                $category = strtoupper((string)($row->category ?? ''));
                if (!array_key_exists($category, $byCategory)) {
                    continue;
                }

                $code = trim((string)($row->part_code ?? ''));
                if ($code === '') {
                    continue;
                }

                $byCategory[$category][] = [
                    'code' => $code,
                    'label' => $this->resolveDisplayName(
                        $code,
                        $row->name ?? null,
                        $row->name_en ?? null,
                        $uiLanguage
                    ),
                ];
            }
        } catch (\Throwable $e) {
            // Keep the default empty structure.
        }

        return [
            'sleeve' => $byCategory['SLEEVE'],
            'fiber' => $byCategory['FIBER'],
            'tube' => $byCategory['TUBE'],
            'connector' => $byCategory['CONNECTOR'],
        ];
    }

    public function resolveDisplayName(
        string $partCode,
        mixed $name,
        mixed $nameEn = null,
        string $uiLanguage = 'ja'
    ): string {
        $baseName = trim((string)$name);
        $englishName = trim((string)$nameEn);
        $isEnglish = strtolower(trim($uiLanguage)) === 'en';

        if (!$isEnglish) {
            return $baseName !== '' ? $baseName : $partCode;
        }

        if ($englishName !== '') {
            return $englishName;
        }

        if ($baseName !== '' && !$this->containsJapanese($baseName)) {
            return $baseName;
        }

        return $partCode !== '' ? $partCode : $baseName;
    }

    private function containsJapanese(string $value): bool
    {
        return (bool)preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $value);
    }

    private function hasNameEnColumn(): bool
    {
        if ($this->hasNameEnColumn !== null) {
            return $this->hasNameEnColumn;
        }

        try {
            $this->hasNameEnColumn = Schema::hasColumn('parts', 'name_en');
        } catch (\Throwable $e) {
            $this->hasNameEnColumn = false;
        }

        return $this->hasNameEnColumn;
    }

    private function hasDeletedAtColumn(): bool
    {
        if ($this->hasDeletedAtColumn !== null) {
            return $this->hasDeletedAtColumn;
        }

        try {
            $this->hasDeletedAtColumn = Schema::hasColumn('parts', 'deleted_at');
        } catch (\Throwable $e) {
            $this->hasDeletedAtColumn = false;
        }

        return $this->hasDeletedAtColumn;
    }
}
