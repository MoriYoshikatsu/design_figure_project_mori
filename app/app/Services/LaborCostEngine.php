<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LaborCostEngine
{
    /**
     * @param array<int, array<string, mixed>> $bom
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function calculate(array $bom, int $orderQty = 1, array $overrides = []): array
    {
        $orderQty = max(1, $orderQty);

        if (
            !$this->hasTable('labor_cost_settings')
            || !$this->hasTable('labor_processes')
            || !$this->hasTable('labor_process_elements')
            || !$this->hasTable('labor_auto_rules')
        ) {
            return [
                'hourly_rate' => 9000.0,
                'order_qty' => $orderQty,
                'matched_labor_rules' => [],
                'matched_process_codes' => [],
                'labor_order_total' => 0.0,
                'labor_unit_cost' => 0.0,
                'labor_breakdown' => [],
                'processes' => [],
            ];
        }

        $hourlyRate = (float)(DB::table('labor_cost_settings')->orderBy('id')->value('hourly_rate') ?? 9000);
        if ($hourlyRate <= 0) {
            $hourlyRate = 9000.0;
        }

        $context = $this->buildBomContext($bom);
        $rules = $this->loadRules();
        $matchedRules = [];
        $selectedProcessIds = [];
        foreach ($rules as $rule) {
            if (!$this->ruleMatches($rule, $context)) {
                continue;
            }
            $matchedRules[] = [
                'rule_id' => (int)$rule['id'],
                'rule_code' => (string)$rule['rule_code'],
                'rule_name' => (string)$rule['name'],
                'process_id' => (int)$rule['process_id'],
                'process_code' => (string)$rule['process_code'],
                'priority' => (int)$rule['priority'],
                'always_apply' => (bool)$rule['always_apply'],
            ];
            $selectedProcessIds[(int)$rule['process_id']] = true;
        }

        if (empty($selectedProcessIds)) {
            return [
                'hourly_rate' => $this->normalizeAmount($hourlyRate),
                'order_qty' => $orderQty,
                'matched_labor_rules' => $matchedRules,
                'matched_process_codes' => [],
                'labor_order_total' => 0.0,
                'labor_unit_cost' => 0.0,
                'labor_breakdown' => [],
                'processes' => [],
            ];
        }

        $processes = $this->loadProcessesWithElements(array_keys($selectedProcessIds));
        $normalizedOverrides = $this->normalizeOverrides($overrides);

        $laborOrderTotal = 0.0;
        $laborUnitCost = 0.0;
        $processBreakdown = [];
        $matchedProcessCodes = [];

        foreach ($processes as $process) {
            $processCode = (string)$process['process_code'];
            $matchedProcessCodes[] = $processCode;

            $processDefaultYield = $this->safeYield((float)($process['default_yield_rate'] ?? 0.0), 1.0);
            $processOverride = $normalizedOverrides['processes'][$processCode] ?? [];
            $processHasOverride = $this->hasYieldInput($processOverride);
            $processYieldMeta = $this->resolveYield($processDefaultYield, $processOverride, 'process_default');
            $processYield = $processYieldMeta['value'];

            $elements = is_array($process['elements'] ?? null) ? $process['elements'] : [];
            $elementsBreakdown = [];
            $elementsSum = 0.0;

            foreach ($elements as $element) {
                $elementCode = (string)$element['element_code'];
                $elementDefaultYield = $this->safeYield((float)($element['default_yield_rate'] ?? 0.0), 1.0);
                $elementOverride = $normalizedOverrides['elements'][$processCode][$elementCode] ?? [];
                $elementYieldMeta = $processHasOverride
                    ? [
                        'value' => $processYield,
                        'source' => 'process_override',
                        'input' => $processYieldMeta['input'],
                    ]
                    : $this->resolveYield($elementDefaultYield, $elementOverride, 'element_default');
                $elementYield = $elementYieldMeta['value'];

                $workMinutes = max(0.0, (float)($element['work_minutes'] ?? 0));
                $activityCoeff = max(0.0, (float)($element['activity_coeff'] ?? 0));
                $batchSize = max(1, (int)($element['batch_size'] ?? 1));
                $depreciationAmount = max(0.0, (float)($element['depreciation_amount'] ?? 0));
                $runs = (float)ceil($orderQty / $batchSize);
                $elementBase = ($hourlyRate * ($workMinutes / 60.0) * $activityCoeff) + $depreciationAmount;
                $elementCost = $elementBase * $runs / max(0.000001, $elementYield);
                $elementsSum += $elementCost;

                $elementsBreakdown[] = [
                    'element_id' => (int)$element['id'],
                    'element_code' => $elementCode,
                    'element_name' => (string)$element['name'],
                    'work_minutes' => $this->normalizeAmount($workMinutes),
                    'activity_coeff' => $this->normalizeAmount($activityCoeff),
                    'batch_size' => $batchSize,
                    'runs' => $this->normalizeAmount($runs),
                    'depreciation_amount' => $this->normalizeAmount($depreciationAmount),
                    'yield_rate_default' => $this->normalizeAmount($elementDefaultYield),
                    'yield_rate_applied' => $this->normalizeAmount($elementYield),
                    'yield_source' => (string)$elementYieldMeta['source'],
                    'yield_input' => $elementYieldMeta['input'],
                    'element_base' => $this->normalizeAmount($elementBase),
                    'element_cost' => $this->normalizeAmount($elementCost),
                ];
            }

            $processRaw = $elementsSum / max(0.000001, $processYield);
            $processOrderTotal = $this->roundUpByUnit($processRaw, 10);
            $processUnit = $processOrderTotal / $orderQty;

            $laborOrderTotal += $processOrderTotal;
            $laborUnitCost += $processUnit;

            $processBreakdown[] = [
                'process_id' => (int)$process['id'],
                'process_code' => $processCode,
                'process_name' => (string)$process['name'],
                'yield_rate_default' => $this->normalizeAmount($processDefaultYield),
                'yield_rate_applied' => $this->normalizeAmount($processYield),
                'yield_source' => (string)$processYieldMeta['source'],
                'yield_input' => $processYieldMeta['input'],
                'elements_total' => $this->normalizeAmount($elementsSum),
                'process_raw' => $this->normalizeAmount($processRaw),
                'process_order_total' => $this->normalizeAmount($processOrderTotal),
                'process_unit' => $this->normalizeAmount($processUnit),
                'elements' => $elementsBreakdown,
            ];
        }

        return [
            'hourly_rate' => $this->normalizeAmount($hourlyRate),
            'order_qty' => $orderQty,
            'matched_labor_rules' => $matchedRules,
            'matched_process_codes' => array_values(array_unique($matchedProcessCodes)),
            'labor_order_total' => $this->normalizeAmount($laborOrderTotal),
            'labor_unit_cost' => $this->normalizeAmount($laborUnitCost),
            'labor_breakdown' => $processBreakdown,
            'processes' => $processBreakdown,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $bom
     * @return array<string, array<string, bool>>
     */
    private function buildBomContext(array $bom): array
    {
        $skuCodes = [];
        foreach ($bom as $row) {
            $code = strtoupper(trim((string)($row['part_code'] ?? ($row['sku_code'] ?? ''))));
            if ($code !== '') {
                $skuCodes[$code] = true;
            }
        }

        $tags = [];
        $categories = [];
        $skuMetaByCode = [];
        if (!empty($skuCodes) && $this->hasTable('parts')) {
            $rows = DB::table('parts')
                ->whereIn('part_code', array_keys($skuCodes))
                ->get(['part_code', 'category', 'attributes']);
            foreach ($rows as $row) {
                $skuCode = strtoupper(trim((string)($row->part_code ?? '')));
                if ($skuCode === '') {
                    continue;
                }
                $skuMetaByCode[$skuCode] = [
                    'category' => strtoupper(trim((string)($row->category ?? ''))),
                    'attributes' => $this->decodeJsonMap($row->attributes),
                ];
            }
        }

        foreach (array_keys($skuCodes) as $skuCode) {
            $meta = $skuMetaByCode[$skuCode] ?? ['category' => '', 'attributes' => []];
            $category = strtoupper(trim((string)($meta['category'] ?? '')));
            $attributes = is_array($meta['attributes'] ?? null) ? $meta['attributes'] : [];
            if ($category === '') {
                $category = $this->inferCategoryFromSkuCode($skuCode);
            }
            if ($category !== '') {
                $categories[$category] = true;
            }

            $tagRows = $attributes['process_tags'] ?? [];
            if (is_array($tagRows)) {
                foreach ($tagRows as $tag) {
                    $normalized = strtolower(trim((string)$tag));
                    if ($normalized !== '') {
                        $tags[$normalized] = true;
                    }
                }
            }

            foreach ($this->inferTagsForSku($skuCode, $category, $attributes) as $tag) {
                $tags[$tag] = true;
            }
        }

        return [
            'tags' => $tags,
            'categories' => $categories,
            'part_codes' => $skuCodes,
        ];
    }

    private function inferCategoryFromSkuCode(string $skuCode): string
    {
        if (str_starts_with($skuCode, 'PROC_')) {
            return 'PROC';
        }
        if (str_starts_with($skuCode, 'FIBER_')) {
            return 'FIBER';
        }
        if (str_starts_with($skuCode, 'TUBE_')) {
            return 'TUBE';
        }
        if (str_starts_with($skuCode, 'SLEEVE_')) {
            return 'SLEEVE';
        }
        if (str_starts_with($skuCode, 'CONN_')) {
            return 'CONNECTOR';
        }

        return '';
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRules(): array
    {
        $rows = DB::table('labor_auto_rules as r')
            ->join('labor_processes as p', 'p.id', '=', 'r.process_id')
            ->whereNull('r.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('r.active', true)
            ->where('p.active', true)
            ->orderBy('r.priority')
            ->orderBy('r.id')
            ->get([
                'r.id',
                'r.rule_code',
                'r.name',
                'r.process_id',
                'r.priority',
                'r.include_tags_json',
                'r.exclude_tags_json',
                'r.required_part_codes_json',
                'r.always_apply',
                'p.process_code',
            ]);

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = $this->normalizeConnectorRule([
                'id' => (int)$row->id,
                'rule_code' => (string)$row->rule_code,
                'name' => (string)$row->name,
                'process_id' => (int)$row->process_id,
                'priority' => (int)$row->priority,
                'include_tags' => $this->normalizeStringList($this->decodeJsonArray($row->include_tags_json), false),
                'exclude_tags' => $this->normalizeStringList($this->decodeJsonArray($row->exclude_tags_json), false),
                'required_categories' => [],
                'required_part_codes' => $this->normalizeStringList($this->decodeJsonArray($row->required_part_codes_json), true),
                'always_apply' => (bool)$row->always_apply,
                'process_code' => (string)$row->process_code,
            ]);
        }

        return $rules;
    }

    /**
     * 旧データ互換: CONN_NORMAL は APC で除外しない。
     *
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function normalizeConnectorRule(array $rule): array
    {
        $processCode = strtoupper(trim((string)($rule['process_code'] ?? '')));
        if ($processCode !== 'CONN_NORMAL') {
            return $rule;
        }

        $excludeTags = is_array($rule['exclude_tags'] ?? null) ? $rule['exclude_tags'] : [];
        $excludeTags = array_values(array_filter(
            $excludeTags,
            static fn($tag): bool => strtolower(trim((string)$tag)) !== 'apc'
        ));
        $rule['exclude_tags'] = $excludeTags;

        return $rule;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, array<string, bool>> $context
     */
    private function ruleMatches(array $rule, array $context): bool
    {
        if (!empty($rule['always_apply'])) {
            return true;
        }

        $tags = $context['tags'] ?? [];
        $skuCodes = $context['part_codes'] ?? [];

        foreach (($rule['include_tags'] ?? []) as $tag) {
            if (!isset($tags[$tag])) {
                return false;
            }
        }
        foreach (($rule['exclude_tags'] ?? []) as $tag) {
            if (isset($tags[$tag])) {
                return false;
            }
        }
        foreach (($rule['required_part_codes'] ?? []) as $skuCode) {
            if (!isset($skuCodes[$skuCode])) {
                return false;
            }
        }

        return !empty($rule['include_tags'])
            || !empty($rule['required_part_codes']);
    }

    /**
     * @param array<int, int> $processIds
     * @return array<int, array<string, mixed>>
     */
    private function loadProcessesWithElements(array $processIds): array
    {
        if (empty($processIds)) {
            return [];
        }

        $processRows = DB::table('labor_processes')
            ->whereIn('id', $processIds)
            ->whereNull('deleted_at')
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'process_code',
                'name',
                'default_yield_rate',
                'sort_order',
            ]);

        if ($processRows->isEmpty()) {
            return [];
        }

        $elementsByProcess = DB::table('labor_process_elements')
            ->whereIn('process_id', $processRows->pluck('id')->all())
            ->whereNull('deleted_at')
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'process_id',
                'element_code',
                'name',
                'work_minutes',
                'activity_coeff',
                'batch_size',
                'depreciation_amount',
                'default_yield_rate',
                'sort_order',
            ])
            ->groupBy('process_id');

        $result = [];
        foreach ($processRows as $processRow) {
            $elements = [];
            foreach (($elementsByProcess->get((int)$processRow->id) ?? collect()) as $elementRow) {
                $elements[] = [
                    'id' => (int)$elementRow->id,
                    'element_code' => (string)$elementRow->element_code,
                    'name' => (string)$elementRow->name,
                    'work_minutes' => (float)$elementRow->work_minutes,
                    'activity_coeff' => (float)$elementRow->activity_coeff,
                    'batch_size' => (int)$elementRow->batch_size,
                    'depreciation_amount' => (float)$elementRow->depreciation_amount,
                    'default_yield_rate' => (float)$elementRow->default_yield_rate,
                    'sort_order' => (int)$elementRow->sort_order,
                ];
            }

            $result[] = [
                'id' => (int)$processRow->id,
                'process_code' => (string)$processRow->process_code,
                'name' => (string)$processRow->name,
                'default_yield_rate' => (float)$processRow->default_yield_rate,
                'sort_order' => (int)$processRow->sort_order,
                'elements' => $elements,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{
     *   processes: array<string, array<string, float|null>>,
     *   elements: array<string, array<string, array<string, float|null>>>
     * }
     */
    private function normalizeOverrides(array $overrides): array
    {
        $result = [
            'processes' => [],
            'elements' => [],
        ];

        $processRows = is_array($overrides['processes'] ?? null) ? $overrides['processes'] : [];
        foreach ($processRows as $processCode => $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalizedCode = strtoupper(trim((string)$processCode));
            if ($normalizedCode === '') {
                continue;
            }
            $result['processes'][$normalizedCode] = $this->normalizeYieldInputRow($row);
        }

        $elementRows = is_array($overrides['elements'] ?? null) ? $overrides['elements'] : [];
        foreach ($elementRows as $processCode => $elementsByCode) {
            if (!is_array($elementsByCode)) {
                continue;
            }
            $normalizedProcessCode = strtoupper(trim((string)$processCode));
            if ($normalizedProcessCode === '') {
                continue;
            }
            foreach ($elementsByCode as $elementCode => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $normalizedElementCode = strtoupper(trim((string)$elementCode));
                if ($normalizedElementCode === '') {
                    continue;
                }
                $result['elements'][$normalizedProcessCode][$normalizedElementCode] = $this->normalizeYieldInputRow($row);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, float|null>
     */
    private function normalizeYieldInputRow(array $row): array
    {
        return [
            'yield_rate' => is_numeric($row['yield_rate'] ?? null) ? (float)$row['yield_rate'] : null,
            'order_qty' => is_numeric($row['order_qty'] ?? null) ? (float)$row['order_qty'] : null,
            'actual_input_qty' => is_numeric($row['actual_input_qty'] ?? null) ? (float)$row['actual_input_qty'] : null,
        ];
    }

    /**
     * @param array<string, float|null> $input
     */
    private function hasYieldInput(array $input): bool
    {
        return is_numeric($input['yield_rate'] ?? null)
            || (
                is_numeric($input['order_qty'] ?? null)
                && is_numeric($input['actual_input_qty'] ?? null)
            );
    }

    /**
     * @param array<string, float|null> $override
     * @return array{value: float, source: string, input: array<string, float|null>}
     */
    private function resolveYield(float $defaultYield, array $override, string $defaultSource): array
    {
        $hasOrderQty = is_numeric($override['order_qty'] ?? null);
        $hasActualInputQty = is_numeric($override['actual_input_qty'] ?? null);
        $inputOrderQty = $hasOrderQty ? (float)$override['order_qty'] : null;
        $actualInputQty = $hasActualInputQty ? (float)$override['actual_input_qty'] : null;
        $yieldRate = is_numeric($override['yield_rate'] ?? null)
            ? (float)$override['yield_rate']
            : null;

        // 比率入力は order_qty / actual_input_qty の双方が入力された場合のみ採用する。
        if ($hasOrderQty && $hasActualInputQty) {
            $ratio = ($inputOrderQty !== null && $inputOrderQty > 0 && $actualInputQty !== null && $actualInputQty > 0)
                ? ($inputOrderQty / $actualInputQty)
                : 1.0;
            return [
                'value' => $this->safeYield($ratio, 1.0),
                'source' => 'order_actual_input_ratio',
                'input' => [
                    'order_qty' => $inputOrderQty,
                    'actual_input_qty' => $actualInputQty,
                    'yield_rate' => null,
                ],
            ];
        }

        if (is_numeric($yieldRate)) {
            return [
                'value' => $this->safeYield($yieldRate, 1.0),
                'source' => 'yield_override',
                'input' => [
                    'order_qty' => null,
                    'actual_input_qty' => null,
                    'yield_rate' => $yieldRate,
                ],
            ];
        }

        return [
            'value' => $this->safeYield($defaultYield, 1.0),
            'source' => $defaultSource,
            'input' => [
                'order_qty' => null,
                'actual_input_qty' => null,
                'yield_rate' => null,
            ],
        ];
    }

    private function safeYield(float $value, float $fallback): float
    {
        if (!is_finite($value) || $value <= 0) {
            $value = $fallback;
        }
        if (!is_finite($value) || $value <= 0) {
            return 1.0;
        }
        return $value;
    }

    private function roundUpByUnit(float $value, float $unit): float
    {
        if ($unit <= 0) {
            return $value;
        }

        return ceil($value / $unit) * $unit;
    }

    private function normalizeAmount(float $value): float
    {
        return round($value, 6);
    }

    /**
     * @param array<int, mixed> $list
     * @return array<int, string>
     */
    private function normalizeStringList(array $list, bool $uppercase): array
    {
        $normalized = [];
        foreach ($list as $value) {
            $v = trim((string)$value);
            if ($v === '') {
                continue;
            }
            $v = $uppercase ? strtoupper($v) : strtolower($v);
            $normalized[$v] = true;
        }
        return array_keys($normalized);
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonMap(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function hasTable(string $table): bool
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            $cache[$table] = Schema::hasTable($table);
        }
        return $cache[$table];
    }
}
