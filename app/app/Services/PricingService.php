<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class PricingService
{
    /**
     * @param array<int, array{sku_code:string, quantity:float|int, options:array, source_path:?string, sort_order:int}> $bom
     * @return array{
     *   price_book_id:?int,
     *   currency:?string,
     *   items:array<int, array>,
     *   subtotal:float,
     *   tax:float,
     *   total:float
     * }
     */
    public function price(int $accountId, array $bom, ?string $asOf = null): array
    {
        $asOf = $asOf ?: now()->toDateString();
        $accountType = (string)DB::table('accounts')->where('id', $accountId)->value('account_type');

        $priceBook = $this->selectPriceBook($accountType, $asOf);
        $priceBookId = $priceBook['id'] ?? null;
        $currency = $priceBook['currency'] ?? null;

        $items = [];
        $subtotal = 0.0;

        $skuCodes = array_values(array_unique(array_filter(array_map(
            fn ($r) => is_array($r) ? ($r['sku_code'] ?? null) : null,
            $bom
        ))));

        $skuIdByCode = [];
        if (!empty($skuCodes)) {
            $skuIdByCode = DB::table('skus')
                ->whereIn('sku_code', $skuCodes)
                ->pluck('id', 'sku_code')
                ->all();
        }

        $pbiBySkuId = [];
        if ($priceBookId && !empty($skuIdByCode)) {
            $pbiBySkuId = DB::table('price_book_items')
                ->where('price_book_id', $priceBookId)
                ->whereIn('sku_id', array_values($skuIdByCode))
                ->get()
                ->keyBy('sku_id')
                ->map(fn ($row) => (array)$row)
                ->all();
        }

        foreach ($bom as $row) {
            if (!is_array($row)) continue;
            $skuCode = (string)($row['sku_code'] ?? '');
            if ($skuCode === '') continue;

            $qty = $this->asNumber($row['quantity'] ?? 1);
            $qty = $qty > 0 ? $qty : 1;
            $options = is_array($row['options'] ?? null) ? $row['options'] : [];

            $skuId = $skuIdByCode[$skuCode] ?? null;
            $pbi = $skuId ? ($pbiBySkuId[$skuId] ?? null) : null;

            $pricingModel = $pbi['pricing_model'] ?? null;
            $unitPrice = null;
            $lineTotal = 0.0;
            $missingPrice = false;

            if (!$priceBookId || !$skuId || !$pbi) {
                $missingPrice = true;
            } else {
                $unitPrice = $this->calcUnitPrice($pricingModel, $pbi, $options);
                $lineTotal = $unitPrice !== null ? $unitPrice * $qty : 0.0;
            }

            $items[] = [
                'sku_code' => $skuCode,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'pricing_model' => $pricingModel,
                'currency' => $currency,
                'source_path' => $row['source_path'] ?? null,
                'sort_order' => $row['sort_order'] ?? 0,
                'missing_price' => $missingPrice,
            ];

            $subtotal += $lineTotal;
        }

        $tax = $subtotal * 0.10;
        $total = $subtotal + $tax;

        return [
            'price_book_id' => $priceBookId,
            'currency' => $currency,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * @return array{id:int,currency:string}|null
     */
    private function selectPriceBook(string $accountType, string $asOf): ?array
    {
        $priority = match ($accountType) {
            'B2B' => ['B2B', 'STANDARD'],
            'B2C' => ['B2C', 'STANDARD'],
            default => ['STANDARD'],
        };

        $candidates = DB::table('price_books')
            ->whereIn('name', $priority)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $asOf);
            })
            ->where(function ($q) use ($asOf) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $asOf);
            })
            ->orderBy('valid_from', 'desc')
            ->orderBy('version', 'desc')
            ->get(['id', 'name', 'currency'])
            ->all();

        foreach ($priority as $name) {
            foreach ($candidates as $row) {
                if ($row->name === $name) {
                    return ['id' => (int)$row->id, 'currency' => (string)$row->currency];
                }
            }
        }

        $row = $candidates[0] ?? null;
        if (!$row) return null;
        return ['id' => (int)$row->id, 'currency' => (string)$row->currency];
    }

    private function calcUnitPrice(?string $model, array $pbi, array $options): ?float
    {
        return match ($model) {
            'FIXED' => $this->asNumber($pbi['unit_price'] ?? null),
            'PER_M', 'PER_MM' => $this->pricePerMeter($pbi) * $this->lengthFromOptions($options),
            'FORMULA' => $this->evalFormula($pbi['formula'] ?? null, $options),
            default => null,
        };
    }

    private function evalFormula(mixed $raw, array $options): ?float
    {
        $formula = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($formula)) return null;

        $type = $formula['type'] ?? null;
        if ($type === 'linear') {
            $base = $this->asNumber($formula['base'] ?? 0);
            $k = $this->asNumber($formula['k'] ?? 0);
            $x = $this->lengthFromOptions($options);
            return $base + $k * $x;
        }

        return null;
    }

    private function lengthFromOptions(array $options): float
    {
        $len = $options['lengthM'] ?? null;
        if (!is_numeric($len)) {
            $legacyLen = $options['lengthMm'] ?? null;
            if (is_numeric($legacyLen)) {
                $len = (float)$legacyLen / 1000;
            }
        }
        if (!is_numeric($len)) {
            $len = $options['totalFiberLengthM'] ?? null;
        }
        if (!is_numeric($len)) {
            $legacyTotalLen = $options['totalFiberLengthMm'] ?? null;
            if (is_numeric($legacyTotalLen)) {
                $len = (float)$legacyTotalLen / 1000;
            }
        }
        return $this->asNumber($len);
    }

    private function pricePerMeter(array $pbi): float
    {
        $perM = $pbi['price_per_m'] ?? null;
        if (is_numeric($perM)) {
            return (float)$perM;
        }

        $legacyPerMm = $pbi['price_per_mm'] ?? null;
        if (is_numeric($legacyPerMm)) {
            return (float)$legacyPerMm * 1000;
        }

        return 0.0;
    }

    private function asNumber(mixed $v): float
    {
        return is_numeric($v) ? (float)$v : 0.0;
    }
}
