<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('price_book_items')) {
            if (!Schema::hasColumn('price_book_items', 'price_per_m')) {
                Schema::table('price_book_items', function (Blueprint $table) {
                    $table->decimal('price_per_m', 12, 6)->nullable();
                });
            }

            $this->dropPricingModelCheckConstraints();

            if (Schema::hasColumn('price_book_items', 'price_per_mm')) {
                DB::statement('UPDATE price_book_items SET price_per_m = COALESCE(price_per_m, price_per_mm * 1000)');
            }

            DB::table('price_book_items')
                ->where('pricing_model', 'PER_MM')
                ->update(['pricing_model' => 'PER_M']);

            $this->migratePriceBookItemFormulaUnits();
            $this->addPricingModelCheckConstraint(['FIXED', 'PER_M', 'FORMULA']);

            if (Schema::hasColumn('price_book_items', 'price_per_mm')) {
                Schema::table('price_book_items', function (Blueprint $table) {
                    $table->dropColumn('price_per_mm');
                });
            }
        }

        $this->migrateJsonColumnUnits('configurator_sessions', 'config');
        $this->migrateJsonColumnUnits('configurator_sessions', 'derived');
        $this->migrateJsonColumnUnits('quotes', 'snapshot');
        $this->migrateJsonColumnUnits('quote_items', 'options');
        $this->migrateJsonColumnUnits('change_requests', 'proposed_json');
    }

    public function down(): void
    {
        if (!Schema::hasTable('price_book_items')) {
            return;
        }

        if (!Schema::hasColumn('price_book_items', 'price_per_mm')) {
            Schema::table('price_book_items', function (Blueprint $table) {
                $table->decimal('price_per_mm', 12, 6)->nullable();
            });
        }

        $this->dropPricingModelCheckConstraints();

        if (Schema::hasColumn('price_book_items', 'price_per_m')) {
            DB::statement('UPDATE price_book_items SET price_per_mm = COALESCE(price_per_mm, price_per_m / 1000)');
        }

        DB::table('price_book_items')
            ->where('pricing_model', 'PER_M')
            ->update(['pricing_model' => 'PER_MM']);

        $this->addPricingModelCheckConstraint(['FIXED', 'PER_MM', 'FORMULA']);

        if (Schema::hasColumn('price_book_items', 'price_per_m')) {
            Schema::table('price_book_items', function (Blueprint $table) {
                $table->dropColumn('price_per_m');
            });
        }
    }

    private function migratePriceBookItemFormulaUnits(): void
    {
        DB::table('price_book_items')
            ->select(['id', 'formula'])
            ->orderBy('id')
            ->chunkById(300, function ($rows) {
                foreach ($rows as $row) {
                    $formula = $this->decodeJsonObject($row->formula ?? null);
                    if (!is_array($formula)) {
                        continue;
                    }

                    $converted = $this->convertUnitTree($formula);
                    if ($converted === $formula) {
                        continue;
                    }

                    DB::table('price_book_items')
                        ->where('id', (int)$row->id)
                        ->update([
                            'formula' => json_encode($converted, JSON_UNESCAPED_UNICODE),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function migrateJsonColumnUnits(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(300, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    $decoded = $this->decodeJsonObject($row->{$column} ?? null);
                    if (!is_array($decoded)) {
                        continue;
                    }

                    $converted = $this->convertUnitTree($decoded);
                    if ($converted === $decoded) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', (int)$row->id)
                        ->update([
                            $column => json_encode($converted, JSON_UNESCAPED_UNICODE),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function convertUnitTree(array $node): array
    {
        $hadLegacyTotalLength = array_key_exists('totalLengthMm', $node);
        $hadLegacySegmentCap = array_key_exists('segmentLengthCapMm', $node);

        foreach ([
            'lengthMm' => 'lengthM',
            'toleranceMm' => 'toleranceM',
            'startOffsetMm' => 'startOffsetM',
            'endOffsetMm' => 'endOffsetM',
            'totalLengthMm' => 'totalLengthM',
            'segmentLengthCapMm' => 'segmentLengthCapM',
            'totalFiberLengthMm' => 'totalFiberLengthM',
        ] as $legacyKey => $newKey) {
            if (!array_key_exists($newKey, $node) && array_key_exists($legacyKey, $node)) {
                $legacyValue = $node[$legacyKey];
                if (is_numeric($legacyValue)) {
                    $node[$newKey] = (float)$legacyValue / 1000;
                } elseif ($legacyValue === null || $legacyValue === '') {
                    $node[$newKey] = $legacyValue;
                }
            }
            unset($node[$legacyKey]);
        }

        if (($hadLegacyTotalLength || $hadLegacySegmentCap) && is_array($node['displaySegmentLens'] ?? null)) {
            $node['displaySegmentLens'] = array_map(function ($v) {
                return is_numeric($v) ? ((float)$v / 1000) : $v;
            }, $node['displaySegmentLens']);
        }

        if (array_key_exists('pricing_model', $node) && strtoupper((string)$node['pricing_model']) === 'PER_MM') {
            $node['pricing_model'] = 'PER_M';
        }

        if (!array_key_exists('price_per_m', $node) && array_key_exists('price_per_mm', $node) && is_numeric($node['price_per_mm'])) {
            $node['price_per_m'] = (float)$node['price_per_mm'] * 1000;
        }
        unset($node['price_per_mm']);

        if (($node['type'] ?? null) === 'linear' && is_numeric($node['k'] ?? null)) {
            $lengthUnit = strtolower((string)($node['length_unit'] ?? ''));
            if ($lengthUnit === '' || $lengthUnit === 'mm') {
                $node['k'] = (float)$node['k'] * 1000;
                $node['length_unit'] = 'm';
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $node[$key] = $this->convertUnitTree($value);
                } else {
                    foreach ($value as $idx => $item) {
                        if (is_array($item)) {
                            $value[$idx] = $this->isAssoc($item)
                                ? $this->convertUnitTree($item)
                                : $this->convertUnitList($item);
                        }
                    }
                    $node[$key] = $value;
                }
            }
        }

        return $node;
    }

    /**
     * @param array<int, mixed> $list
     * @return array<int, mixed>
     */
    private function convertUnitList(array $list): array
    {
        foreach ($list as $idx => $item) {
            if (is_array($item)) {
                $list[$idx] = $this->isAssoc($item)
                    ? $this->convertUnitTree($item)
                    : $this->convertUnitList($item);
            }
        }

        return $list;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_object($raw)) {
            $raw = json_encode($raw, JSON_UNESCAPED_UNICODE);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function dropPricingModelCheckConstraints(): void
    {
        $constraints = DB::select(<<<'SQL'
SELECT c.conname
FROM pg_constraint c
JOIN pg_class t ON c.conrelid = t.oid
JOIN pg_namespace n ON n.oid = t.relnamespace
WHERE n.nspname = current_schema()
  AND t.relname = 'price_book_items'
  AND c.contype = 'c'
  AND pg_get_constraintdef(c.oid) ILIKE '%pricing_model%'
SQL);

        foreach ($constraints as $constraint) {
            $name = str_replace('"', '""', (string)$constraint->conname);
            DB::statement('ALTER TABLE price_book_items DROP CONSTRAINT IF EXISTS "' . $name . '"');
        }
    }

    /**
     * @param array<int, string> $values
     */
    private function addPricingModelCheckConstraint(array $values): void
    {
        $escaped = array_map(static fn (string $v): string => str_replace("'", "''", $v), $values);
        $list = "'" . implode("','", $escaped) . "'";

        DB::statement('ALTER TABLE price_book_items ADD CONSTRAINT price_book_items_pricing_model_check CHECK (pricing_model::text = ANY (ARRAY[' . $list . ']::text[]))');
    }
};
