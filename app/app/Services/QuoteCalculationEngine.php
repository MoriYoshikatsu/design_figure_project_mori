<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class QuoteCalculationEngine
{
    /**
     * @param array<int, array<string, mixed>> $bom
     * @param array<int, array<string, mixed>> $pricingItems
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function calculate(int $accountId, array $bom, array $pricingItems, array $input = []): array
    {
        $currency = strtoupper(trim((string)($input['currency'] ?? 'JPY')));
        if ($currency === '') {
            $currency = 'JPY';
        }

        $policy = $this->resolvePolicy(isset($input['pricing_policy_id']) ? (int)$input['pricing_policy_id'] : null);
        $accountDefaults = $this->resolveAccountDefaults($accountId);

        $orderQty = max(1, (int)($input['order_qty'] ?? 1));
        $tradeScope = $this->normalizeTradeScope((string)($input['trade_scope'] ?? $accountDefaults['trade_scope_default']));

        $fixedCost = $this->asNumberOr($input['fixed_cost'] ?? null, (float)$policy['fixed_cost_default']);
        $managementFactor = $this->asNumberOr(
            $input['management_factor'] ?? null,
            $this->resolveTierFactor($orderQty, $policy['management_tiers'], 1.0)
        );
        $qtyDiscountFactor = $this->asNumberOr(
            $input['qty_discount_factor'] ?? null,
            $this->resolveTierFactor($orderQty, $policy['quantity_discount_tiers'], 1.0)
        );
        $customerFactor = $this->asNumberOr(
            $input['customer_factor'] ?? null,
            (float)$accountDefaults['customer_factor_default']
        );

        $freightDefault = $tradeScope === 'DOMESTIC' ? (float)$policy['domestic_freight_default'] : 0.0;
        $freightAmount = $this->asNumberOr($input['freight_amount'] ?? null, $freightDefault);

        $manualDiscountAmount = $this->asNumberOr($input['manual_discount_amount'] ?? null, 0.0);
        if ($manualDiscountAmount > 0) {
            throw new \InvalidArgumentException('manual_discount_amount must be 0 or negative.');
        }

        $taxRateDefault = $tradeScope === 'DOMESTIC'
            ? (float)$policy['domestic_tax_rate']
            : (float)$policy['overseas_tax_rate'];
        $taxRate = $this->asNumberOr($input['tax_rate'] ?? null, $taxRateDefault);

        $rounding = $this->resolveRounding($policy['rounding_rules'], $currency);
        $roundingUnit = max(0.000001, (float)($rounding['unit'] ?? 1));
        $roundingMode = (string)($rounding['mode'] ?? 'ROUNDUP');

        $step0 = $this->buildStep0($bom, $pricingItems);
        $partsUnitCost = $step0['parts_unit_cost'];
        $laborUnitCost = $step0['labor_unit_cost'];
        $variableUnitCost = $step0['variable_unit_cost'];

        $subtotalRaw = ($fixedCost + ($variableUnitCost * $orderQty))
            * $managementFactor
            * $qtyDiscountFactor
            * $customerFactor;

        $unitPriceRaw = $subtotalRaw / max(1, $orderQty);
        $unitPriceRounded = $this->roundUpByUnit($unitPriceRaw, $roundingUnit);

        $recomputedTotal = $unitPriceRounded * $orderQty;
        $adjustedTotal = $recomputedTotal + $freightAmount + $manualDiscountAmount;
        $taxAmount = $adjustedTotal * $taxRate;
        $grandTotal = $adjustedTotal + $taxAmount;

        $pricingInput = [
            'order_qty' => $orderQty,
            'fixed_cost' => $fixedCost,
            'management_factor' => $managementFactor,
            'qty_discount_factor' => $qtyDiscountFactor,
            'customer_factor' => $customerFactor,
            'freight_amount' => $freightAmount,
            'manual_discount_amount' => $manualDiscountAmount,
            'trade_scope' => $tradeScope,
            'tax_rate' => $taxRate,
            'pricing_policy_id' => (int)$policy['id'],
            'rounding_currency' => $currency,
            'rounding_unit' => $roundingUnit,
            'rounding_mode' => $roundingMode,
        ];

        $pricingSteps = [
            'step0' => array_merge($step0, [
                'parts_unit_cost' => $this->normalizeAmount($partsUnitCost),
                'labor_unit_cost' => $this->normalizeAmount($laborUnitCost),
                'variable_unit_cost' => $this->normalizeAmount($variableUnitCost),
            ]),
            'step1' => [
                'subtotal_raw' => $this->normalizeAmount($subtotalRaw),
            ],
            'step2' => [
                'unit_price_raw' => $this->normalizeAmount($unitPriceRaw),
                'unit_price_rounded' => $this->normalizeAmount($unitPriceRounded),
                'rounding_currency' => $currency,
                'rounding_unit' => $this->normalizeAmount($roundingUnit),
                'rounding_mode' => $roundingMode,
            ],
            'step3' => [
                'recomputed_total' => $this->normalizeAmount($recomputedTotal),
            ],
            'step4' => [
                'adjusted_total' => $this->normalizeAmount($adjustedTotal),
                'freight_amount' => $this->normalizeAmount($freightAmount),
                'manual_discount_amount' => $this->normalizeAmount($manualDiscountAmount),
            ],
            'step5' => [
                'tax_rate' => $this->normalizeAmount($taxRate),
                'tax_amount' => $this->normalizeAmount($taxAmount),
            ],
            'step6' => [
                'grand_total' => $this->normalizeAmount($grandTotal),
            ],
        ];

        $pricingOutput = [
            'subtotal_raw' => $this->normalizeAmount($subtotalRaw),
            'unit_price_raw' => $this->normalizeAmount($unitPriceRaw),
            'unit_price_rounded' => $this->normalizeAmount($unitPriceRounded),
            'recomputed_total' => $this->normalizeAmount($recomputedTotal),
            'adjusted_total' => $this->normalizeAmount($adjustedTotal),
            'tax_rate' => $this->normalizeAmount($taxRate),
            'tax_amount' => $this->normalizeAmount($taxAmount),
            'grand_total' => $this->normalizeAmount($grandTotal),
            'rounding_currency' => $currency,
            'rounding_unit' => $this->normalizeAmount($roundingUnit),
            'rounding_mode' => $roundingMode,
        ];

        return [
            'pricing_policy_id' => (int)$policy['id'],
            'pricing_input' => $pricingInput,
            'pricing_steps' => $pricingSteps,
            'pricing_output' => $pricingOutput,
            'totals' => [
                'subtotal' => $this->normalizeAmount($adjustedTotal),
                'tax' => $this->normalizeAmount($taxAmount),
                'total' => $this->normalizeAmount($grandTotal),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultInputsForAccount(int $accountId, ?string $currency = 'JPY'): array
    {
        $policy = $this->resolvePolicy(null);
        $accountDefaults = $this->resolveAccountDefaults($accountId);
        $tradeScope = $this->normalizeTradeScope((string)$accountDefaults['trade_scope_default']);
        $taxRate = $tradeScope === 'DOMESTIC'
            ? (float)$policy['domestic_tax_rate']
            : (float)$policy['overseas_tax_rate'];

        $resolvedCurrency = strtoupper(trim((string)$currency));
        if ($resolvedCurrency === '') {
            $resolvedCurrency = 'JPY';
        }

        $rounding = $this->resolveRounding($policy['rounding_rules'], $resolvedCurrency);

        return [
            'order_qty' => 1,
            'fixed_cost' => (float)$policy['fixed_cost_default'],
            'management_factor' => $this->resolveTierFactor(1, $policy['management_tiers'], 1.0),
            'qty_discount_factor' => $this->resolveTierFactor(1, $policy['quantity_discount_tiers'], 1.0),
            'customer_factor' => (float)$accountDefaults['customer_factor_default'],
            'freight_amount' => $tradeScope === 'DOMESTIC' ? (float)$policy['domestic_freight_default'] : 0.0,
            'manual_discount_amount' => 0.0,
            'trade_scope' => $tradeScope,
            'tax_rate' => $taxRate,
            'pricing_policy_id' => (int)$policy['id'],
            'rounding_currency' => $resolvedCurrency,
            'rounding_unit' => (float)($rounding['unit'] ?? 1),
            'rounding_mode' => (string)($rounding['mode'] ?? 'ROUNDUP'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $bom
     * @param array<int, array<string, mixed>> $pricingItems
     * @return array<string, mixed>
     */
    private function buildStep0(array $bom, array $pricingItems): array
    {
        $pricingBySortOrder = [];
        foreach ($pricingItems as $item) {
            $sortOrder = (int)($item['sort_order'] ?? 0);
            $pricingBySortOrder[$sortOrder] = $item;
        }

        $skuCodes = [];
        foreach ($bom as $row) {
            $code = trim((string)($row['sku_code'] ?? ''));
            if ($code !== '') {
                $skuCodes[$code] = true;
            }
        }

        $skuMetaByCode = [];
        if (!empty($skuCodes)) {
            $rows = DB::table('skus')
                ->whereIn('sku_code', array_keys($skuCodes))
                ->get(['id', 'sku_code', 'category']);
            foreach ($rows as $row) {
                $skuMetaByCode[(string)$row->sku_code] = [
                    'id' => (int)$row->id,
                    'category' => strtoupper((string)$row->category),
                ];
            }
        }

        $procSkuIds = [];
        foreach ($bom as $index => $row) {
            $skuCode = trim((string)($row['sku_code'] ?? ''));
            if ($skuCode === '') {
                continue;
            }
            $meta = $skuMetaByCode[$skuCode] ?? null;
            if (!$meta) {
                continue;
            }
            if (($meta['category'] ?? '') === 'PROC') {
                $procSkuIds[(int)$meta['id']] = true;
            }
        }

        $laborBySkuId = [];
        if (!empty($procSkuIds) && $this->hasTable('processing_labor_costs')) {
            $laborRows = DB::table('processing_labor_costs')
                ->whereNull('deleted_at')
                ->where('active', true)
                ->whereIn('sku_id', array_keys($procSkuIds))
                ->orderByDesc('id')
                ->get();
            foreach ($laborRows as $laborRow) {
                $skuId = (int)$laborRow->sku_id;
                if (!isset($laborBySkuId[$skuId])) {
                    $laborBySkuId[$skuId] = [
                        'id' => (int)$laborRow->id,
                        'labor_time_hours' => (float)$laborRow->labor_time_hours,
                        'hourly_rate' => (float)$laborRow->hourly_rate,
                        'activity_coeff' => (float)$laborRow->activity_coeff,
                        'yield_rate' => (float)$laborRow->yield_rate,
                        'consumables_amount' => (float)$laborRow->consumables_amount,
                        'packaging_amount' => (float)$laborRow->packaging_amount,
                        'fixed_process_amount' => (float)$laborRow->fixed_process_amount,
                    ];
                }
            }
        }

        $partsUnitCost = 0.0;
        $laborUnitCost = 0.0;
        $partsBreakdown = [];
        $laborBreakdown = [];

        foreach ($bom as $index => $row) {
            $skuCode = trim((string)($row['sku_code'] ?? ''));
            if ($skuCode === '') {
                continue;
            }

            $sortOrder = (int)($row['sort_order'] ?? $index);
            $qty = $this->asNumberOr($row['quantity'] ?? null, 1.0);
            if ($qty <= 0) {
                $qty = 1.0;
            }
            $meta = $skuMetaByCode[$skuCode] ?? ['id' => 0, 'category' => ''];
            $category = (string)($meta['category'] ?? '');

            $priceItem = $pricingBySortOrder[$sortOrder] ?? [];
            $lineTotal = $this->asNumberOr($priceItem['line_total'] ?? null, 0.0);

            if ($category !== 'PROC') {
                $partsUnitCost += $lineTotal;
                $partsBreakdown[] = [
                    'sku_code' => $skuCode,
                    'quantity' => $this->normalizeAmount($qty),
                    'line_total' => $this->normalizeAmount($lineTotal),
                    'category' => $category,
                ];
                continue;
            }

            $skuId = (int)($meta['id'] ?? 0);
            $laborRow = $laborBySkuId[$skuId] ?? null;
            if (!$laborRow) {
                $laborUnitCost += $lineTotal;
                $laborBreakdown[] = [
                    'sku_code' => $skuCode,
                    'quantity' => $this->normalizeAmount($qty),
                    'missing_labor_master' => true,
                    'fallback_line_total' => $this->normalizeAmount($lineTotal),
                ];
                continue;
            }

            $yieldRate = max((float)$laborRow['yield_rate'], 0.000001);
            $laborRaw =
                (float)$laborRow['fixed_process_amount']
                + (float)$laborRow['consumables_amount']
                + (float)$laborRow['packaging_amount']
                + ((float)$laborRow['hourly_rate'] * (float)$laborRow['labor_time_hours'] * (float)$laborRow['activity_coeff']);

            $laborAdjusted = $laborRaw / $yieldRate;
            $laborRoundedUnit = $this->roundUpByUnit($laborAdjusted, 10);
            $laborLineTotal = $laborRoundedUnit * $qty;
            $laborUnitCost += $laborLineTotal;

            $laborBreakdown[] = [
                'sku_code' => $skuCode,
                'quantity' => $this->normalizeAmount($qty),
                'labor_raw' => $this->normalizeAmount($laborRaw),
                'labor_adjusted' => $this->normalizeAmount($laborAdjusted),
                'labor_unit_rounded' => $this->normalizeAmount($laborRoundedUnit),
                'labor_line_total' => $this->normalizeAmount($laborLineTotal),
                'yield_rate' => $this->normalizeAmount($yieldRate),
                'hourly_rate' => $this->normalizeAmount((float)$laborRow['hourly_rate']),
                'labor_time_hours' => $this->normalizeAmount((float)$laborRow['labor_time_hours']),
                'activity_coeff' => $this->normalizeAmount((float)$laborRow['activity_coeff']),
            ];
        }

        return [
            'parts_unit_cost' => $this->normalizeAmount($partsUnitCost),
            'labor_unit_cost' => $this->normalizeAmount($laborUnitCost),
            'variable_unit_cost' => $this->normalizeAmount($partsUnitCost + $laborUnitCost),
            'parts_breakdown' => $partsBreakdown,
            'labor_breakdown' => $laborBreakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePolicy(?int $policyId = null): array
    {
        if (!$this->hasTable('quote_pricing_policies')) {
            return [
                'id' => 0,
                'fixed_cost_default' => 6000.0,
                'management_tiers' => [
                    ['min' => 0, 'max' => 49, 'factor' => 1.20],
                    ['min' => 50, 'max' => 199, 'factor' => 1.15],
                    ['min' => 200, 'max' => null, 'factor' => 1.13],
                ],
                'quantity_discount_tiers' => [
                    ['min' => 0, 'max' => 99, 'factor' => 1.00],
                    ['min' => 100, 'max' => 299, 'factor' => 0.98],
                    ['min' => 300, 'max' => null, 'factor' => 0.95],
                ],
                'domestic_freight_default' => 3000.0,
                'domestic_tax_rate' => 0.10,
                'overseas_tax_rate' => 0.00,
                'rounding_rules' => [
                    'JPY' => ['unit' => 100, 'mode' => 'ROUNDUP'],
                    'DEFAULT' => ['unit' => 1, 'mode' => 'ROUNDUP'],
                ],
            ];
        }

        $query = DB::table('quote_pricing_policies');
        if ($policyId && $policyId > 0) {
            $row = $query->where('id', $policyId)->first();
            if ($row) {
                return $this->normalizePolicyRow($row);
            }
        }

        $today = now()->toDateString();
        $row = DB::table('quote_pricing_policies')
            ->where(function ($q) use ($today) {
                $q->whereNull('active_from')->orWhere('active_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('active_to')->orWhere('active_to', '>=', $today);
            })
            ->orderBy('active_from', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($row) {
            return $this->normalizePolicyRow($row);
        }

        return [
            'id' => 0,
            'fixed_cost_default' => 6000.0,
            'management_tiers' => [
                ['min' => 0, 'max' => 49, 'factor' => 1.20],
                ['min' => 50, 'max' => 199, 'factor' => 1.15],
                ['min' => 200, 'max' => null, 'factor' => 1.13],
            ],
            'quantity_discount_tiers' => [
                ['min' => 0, 'max' => 99, 'factor' => 1.00],
                ['min' => 100, 'max' => 299, 'factor' => 0.98],
                ['min' => 300, 'max' => null, 'factor' => 0.95],
            ],
            'domestic_freight_default' => 3000.0,
            'domestic_tax_rate' => 0.10,
            'overseas_tax_rate' => 0.00,
            'rounding_rules' => [
                'JPY' => ['unit' => 100, 'mode' => 'ROUNDUP'],
                'DEFAULT' => ['unit' => 1, 'mode' => 'ROUNDUP'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePolicyRow(object $row): array
    {
        $managementTiers = $this->decodeJsonArray($row->management_tiers_json);
        $quantityDiscountTiers = $this->decodeJsonArray($row->quantity_discount_tiers_json);
        $roundingRules = $this->decodeJsonMap($row->rounding_rules_json);

        return [
            'id' => (int)$row->id,
            'fixed_cost_default' => (float)$row->fixed_cost_default,
            'management_tiers' => $managementTiers,
            'quantity_discount_tiers' => $quantityDiscountTiers,
            'domestic_freight_default' => (float)$row->domestic_freight_default,
            'domestic_tax_rate' => (float)$row->domestic_tax_rate,
            'overseas_tax_rate' => (float)$row->overseas_tax_rate,
            'rounding_rules' => $roundingRules,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAccountDefaults(int $accountId): array
    {
        if ($accountId <= 0 || !$this->hasTable('accounts')) {
            return [
                'customer_factor_default' => 1.0,
                'trade_scope_default' => 'DOMESTIC',
            ];
        }

        $selects = [];
        if ($this->hasColumn('accounts', 'customer_factor_default')) {
            $selects[] = 'customer_factor_default';
        }
        if ($this->hasColumn('accounts', 'trade_scope_default')) {
            $selects[] = 'trade_scope_default';
        }
        if (empty($selects)) {
            return [
                'customer_factor_default' => 1.0,
                'trade_scope_default' => 'DOMESTIC',
            ];
        }

        $row = DB::table('accounts')
            ->where('id', $accountId)
            ->first($selects);
        if (!$row) {
            return [
                'customer_factor_default' => 1.0,
                'trade_scope_default' => 'DOMESTIC',
            ];
        }

        return [
            'customer_factor_default' => (
                property_exists($row, 'customer_factor_default') && is_numeric($row->customer_factor_default)
                    ? (float)$row->customer_factor_default
                    : 1.0
            ),
            'trade_scope_default' => $this->normalizeTradeScope(
                property_exists($row, 'trade_scope_default')
                    ? (string)($row->trade_scope_default ?? 'DOMESTIC')
                    : 'DOMESTIC'
            ),
        ];
    }

    /**
     * @param array<int, mixed> $tiers
     */
    private function resolveTierFactor(int $orderQty, array $tiers, float $fallback): float
    {
        foreach ($tiers as $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $min = isset($tier['min']) && is_numeric($tier['min']) ? (float)$tier['min'] : 0.0;
            $max = isset($tier['max']) && is_numeric($tier['max']) ? (float)$tier['max'] : null;
            $factor = isset($tier['factor']) && is_numeric($tier['factor']) ? (float)$tier['factor'] : null;
            if ($factor === null) {
                continue;
            }

            if ($orderQty < $min) {
                continue;
            }
            if ($max !== null && $orderQty > $max) {
                continue;
            }

            return $factor;
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    private function resolveRounding(array $rules, string $currency): array
    {
        $currency = strtoupper(trim($currency));
        $default = [
            'unit' => $currency === 'JPY' ? 100 : 1,
            'mode' => 'ROUNDUP',
        ];

        $entry = $rules[$currency] ?? ($rules['DEFAULT'] ?? null);
        if (!is_array($entry)) {
            return $default;
        }

        $unit = isset($entry['unit']) && is_numeric($entry['unit']) ? (float)$entry['unit'] : $default['unit'];
        $mode = strtoupper(trim((string)($entry['mode'] ?? 'ROUNDUP')));
        if ($mode === '') {
            $mode = 'ROUNDUP';
        }

        return [
            'unit' => $unit,
            'mode' => $mode,
        ];
    }

    private function normalizeTradeScope(string $tradeScope): string
    {
        return strtoupper(trim($tradeScope)) === 'OVERSEAS' ? 'OVERSEAS' : 'DOMESTIC';
    }

    private function roundUpByUnit(float $value, float $unit): float
    {
        if ($unit <= 0) {
            return $value;
        }

        return ceil($value / $unit) * $unit;
    }

    private function asNumberOr(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float)$value : $default;
    }

    private function normalizeAmount(float $value): float
    {
        return round($value, 6);
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

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . $column;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $cache[$key];
    }
}
