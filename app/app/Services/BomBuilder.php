<?php

namespace App\Services;

final class BomBuilder
{
    /**
     * @return array<int, array{sku_code:string, quantity:float|int, options:array, source_path:?string, sort_order:int}>
     */
    public function build(array $config, array $derived, array $dsl): array
    {
        $defs = $dsl['bom'] ?? null;
        if (!is_array($defs)) {
            return $this->buildDefault($config, $derived);
        }

        $items = [];
        $sort = 0;

        foreach ($defs as $def) {
            if (!is_array($def)) continue;
            $type = (string)($def['type'] ?? '');
            if ($type === '') continue;

            if ($type === 'addItem') {
                $item = $this->buildItemFromDef($def, $config, $derived, null, null, $sort);
                if ($item) {
                    $items[] = $item;
                    $sort++;
                }
                continue;
            }

            if ($type === 'addItemIfNotNull') {
                $field = $def['field'] ?? null;
                $value = $this->evalExpr($field, $config, $derived, null, null);
                if ($this->isEmpty($value)) continue;

                $item = $this->buildItemFromDef($def, $config, $derived, null, null, $sort);
                if ($item) {
                    $items[] = $item;
                    $sort++;
                }
                continue;
            }

            if ($type === 'addItemsFromArray') {
                $arrayPath = $def['array'] ?? null;
                $arr = $this->evalExpr($arrayPath, $config, $derived, null, null);
                if (!is_array($arr)) continue;

                $skuField = (string)($def['skuCodeField'] ?? 'skuCode');
                $sourceTpl = (string)($def['sourcePathTemplate'] ?? '');

                foreach ($arr as $idx => $row) {
                    if (!is_array($row)) continue;
                    $skuCode = $row[$skuField] ?? null;
                    if ($this->isEmpty($skuCode)) continue;

                    $options = $this->buildOptions($def['optionsMap'] ?? [], $config, $derived, $row, (int)$idx);
                    $sourcePath = $sourceTpl !== '' ? str_replace('{index}', (string)$idx, $sourceTpl) : null;

                    $items[] = $this->normalizeItem([
                        'sku_code' => (string)$skuCode,
                        'quantity' => $this->evalExpr($def['qtyExpr'] ?? 1, $config, $derived, $row, (int)$idx),
                        'options' => $options,
                        'source_path' => $sourcePath,
                        'sort_order' => $sort,
                    ], $config, $derived);
                    $sort++;
                }
            }
        }

        return $items;
    }

    private function buildItemFromDef(array $def, array $config, array $derived, ?array $item, ?int $index, int $sort): ?array
    {
        $sku = $def['skuCodeExpr'] ?? $def['skuCode'] ?? null;
        $skuCode = $this->evalExpr($sku, $config, $derived, $item, $index);
        if ($this->isEmpty($skuCode)) return null;

        $options = $this->buildOptions($def['options'] ?? [], $config, $derived, $item, $index);
        $sourcePath = $def['sourcePath'] ?? null;
        if (!is_string($sourcePath) || $sourcePath === '') {
            $sourcePath = null;
        }

        return $this->normalizeItem([
            'sku_code' => (string)$skuCode,
            'quantity' => $this->evalExpr($def['qtyExpr'] ?? 1, $config, $derived, $item, $index),
            'options' => $options,
            'source_path' => $sourcePath,
            'sort_order' => $sort,
        ], $config, $derived);
    }

    private function buildDefault(array $config, array $derived): array
    {
        $items = [];
        $sort = 0;

        $mfdCount = (int)($config['mfdCount'] ?? 1);
        $totalFiberLengthM = $this->sumLengths($config['fibers'] ?? []);

        $items[] = $this->normalizeItem([
            'sku_code' => 'PROC_MFD_CONVERSION',
            'quantity' => $mfdCount,
            'options' => [
                'mfdCount' => $mfdCount,
                'totalFiberLengthM' => $totalFiberLengthM,
            ],
            'source_path' => null,
            'sort_order' => $sort,
        ], $config, $derived);
        $sort++;

        $sleeves = $config['sleeves'] ?? [];
        foreach ($sleeves as $i => $s) {
            $skuCode = $s['skuCode'] ?? null;
            if ($this->isEmpty($skuCode)) continue;
            $items[] = $this->normalizeItem([
                'sku_code' => (string)$skuCode,
                'quantity' => 1,
                'options' => [],
                'source_path' => "\$.sleeves[$i]",
                'sort_order' => $sort,
            ], $config, $derived);
            $sort++;
        }

        $fibers = $config['fibers'] ?? [];
        foreach ($fibers as $i => $f) {
            $skuCode = $f['skuCode'] ?? null;
            if ($this->isEmpty($skuCode)) continue;
            $items[] = $this->normalizeItem([
                'sku_code' => (string)$skuCode,
                'quantity' => 1,
                'options' => [
                    'lengthM' => $this->extractLengthM($f, 'lengthM', 'lengthMm'),
                    'toleranceM' => $this->extractLengthM($f, 'toleranceM', 'toleranceMm'),
                ],
                'source_path' => "\$.fibers[$i]",
                'sort_order' => $sort,
            ], $config, $derived);
            $sort++;
        }

        $tubes = $config['tubes'] ?? [];
        foreach ($tubes as $i => $t) {
            $skuCode = $t['skuCode'] ?? null;
            if ($this->isEmpty($skuCode)) continue;
            $tubeLen = $this->resolveTubeLengthM($t, $config);
            $items[] = $this->normalizeItem([
                'sku_code' => (string)$skuCode,
                'quantity' => 1,
                'options' => [
                    'startFiberIndex' => $t['startFiberIndex'] ?? null,
                    'startOffsetM' => $this->extractLengthM($t, 'startOffsetM', 'startOffsetMm'),
                    'endFiberIndex' => $t['endFiberIndex'] ?? null,
                    'endOffsetM' => $this->extractLengthM($t, 'endOffsetM', 'endOffsetMm'),
                    'targetFiberIndex' => $t['targetFiberIndex'] ?? null,
                    'lengthM' => $tubeLen,
                    'toleranceM' => $this->extractLengthM($t, 'toleranceM', 'toleranceMm'),
                ],
                'source_path' => "\$.tubes[$i]",
                'sort_order' => $sort,
            ], $config, $derived);
            $sort++;
        }

        $conns = $config['connectors'] ?? [];
        if (!empty($conns['leftSkuCode'])) {
            $items[] = $this->normalizeItem([
                'sku_code' => (string)$conns['leftSkuCode'],
                'quantity' => 1,
                'options' => [],
                'source_path' => "\$.connectors.leftSkuCode",
                'sort_order' => $sort,
            ], $config, $derived);
            $sort++;
        }
        if (!empty($conns['rightSkuCode'])) {
            $items[] = $this->normalizeItem([
                'sku_code' => (string)$conns['rightSkuCode'],
                'quantity' => 1,
                'options' => [],
                'source_path' => "\$.connectors.rightSkuCode",
                'sort_order' => $sort,
            ], $config, $derived);
            $sort++;
        }

        return $items;
    }

    private function resolveTubeLengthM(array $tube, array $config): ?float
    {
        $startIdx = $tube['startFiberIndex'] ?? null;
        $endIdx = $tube['endFiberIndex'] ?? null;
        $startOffset = $this->extractLengthM($tube, 'startOffsetM', 'startOffsetMm');
        $endOffset = $this->extractLengthM($tube, 'endOffsetM', 'endOffsetMm');
        if (is_numeric($startIdx) && is_numeric($endIdx) && is_numeric($startOffset) && is_numeric($endOffset)) {
            $fibers = $config['fibers'] ?? [];
            $fiberCount = count($fibers);
            $si = (int)$startIdx;
            $ei = (int)$endIdx;
            if ($si < 0 || $ei < 0 || $si >= $fiberCount || $ei >= $fiberCount) {
                return $this->extractLengthM($tube, 'lengthM', 'lengthMm');
            }
            $segLens = [];
            foreach ($fibers as $f) {
                $len = $this->extractLengthM($f, 'lengthM', 'lengthMm');
                $segLens[] = (is_numeric($len) && (float)$len > 0) ? (float)$len : 0.0;
            }
            $cum = 0.0;
            $startAbs = 0.0;
            $endAbs = 0.0;
            for ($i = 0; $i < $fiberCount; $i++) {
                if ($i === $si) $startAbs = $cum + (float)$startOffset;
                if ($i === $ei) $endAbs = $cum + (float)$endOffset;
                $cum += $segLens[$i] ?? 0.0;
            }
            $len = $endAbs - $startAbs;
            return $len >= 0 ? $len : null;
        }

        return $this->extractLengthM($tube, 'lengthM', 'lengthMm');
    }

    private function buildOptions(mixed $map, array $config, array $derived, ?array $item, ?int $index): array
    {
        if (!is_array($map)) return [];
        $options = [];
        foreach ($map as $key => $expr) {
            $options[$key] = $this->evalExpr($expr, $config, $derived, $item, $index);
        }
        return $options;
    }

    private function normalizeItem(array $item, array $config, array $derived): array
    {
        $sku = (string)($item['sku_code'] ?? '');
        if ($sku === '') return $item;

        if ($sku === 'PROC_MFD_CONVERSION') {
            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            $options['mfdCount'] = (int)($config['mfdCount'] ?? ($options['mfdCount'] ?? 1));
            if (!array_key_exists('totalFiberLengthM', $options) || !is_numeric($options['totalFiberLengthM'])) {
                $legacyTotal = $options['totalFiberLengthMm'] ?? null;
                if (is_numeric($legacyTotal)) {
                    $options['totalFiberLengthM'] = (float)$legacyTotal / 1000;
                } else {
                    $options['totalFiberLengthM'] = $this->sumLengths($config['fibers'] ?? []);
                }
            }
            unset($options['totalFiberLengthMm']);
            $options['fiberItems'] = $options['fiberItems'] ?? $this->collectFiberItems($config['fibers'] ?? []);
            $item['options'] = $options;
        }

        return $item;
    }

    private function collectFiberItems(array $fibers): array
    {
        $items = [];
        foreach ($fibers as $f) {
            if (!is_array($f)) continue;
            $items[] = [
                'skuCode' => $f['skuCode'] ?? null,
                'lengthM' => $this->extractLengthM($f, 'lengthM', 'lengthMm'),
                'toleranceM' => $this->extractLengthM($f, 'toleranceM', 'toleranceMm'),
            ];
        }
        return $items;
    }

    private function sumLengths(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $len = $this->extractLengthM($it, 'lengthM', 'lengthMm');
            if (is_numeric($len)) {
                $sum += (float)$len;
            }
        }
        return $sum;
    }

    private function extractLengthM(array $row, string $primaryKey, string $legacyKey): ?float
    {
        $v = $row[$primaryKey] ?? null;
        if (is_numeric($v)) {
            return (float)$v;
        }

        $legacy = $row[$legacyKey] ?? null;
        if (is_numeric($legacy)) {
            return (float)$legacy / 1000;
        }

        return null;
    }

    private function evalExpr(mixed $expr, array $config, array $derived, ?array $item, ?int $index): mixed
    {
        if (is_array($expr) && array_key_exists('var', $expr)) {
            return $this->resolveVar((string)$expr['var'], $config, $derived, $item, $index);
        }

        if (is_string($expr) && str_starts_with($expr, '$.')) {
            return $this->resolveVar($expr, $config, $derived, $item, $index);
        }

        return $expr;
    }

    private function resolveVar(string $path, array $config, array $derived, ?array $item, ?int $index): mixed
    {
        if (!str_starts_with($path, '$.')) return null;
        $path = substr($path, 2);

        if (str_starts_with($path, 'item.')) {
            $path = substr($path, 5);
            return $this->dig($item ?? [], $path);
        }

        if (str_starts_with($path, 'derived.')) {
            $path = substr($path, 8);
            return $this->dig($derived, $path);
        }

        return $this->dig($config, $path);
    }

    private function dig(array $base, string $path): mixed
    {
        $segments = preg_split('/\./', $path);
        $cur = $base;

        foreach ($segments as $seg) {
            if ($seg === '') continue;
            if (preg_match('/^([a-zA-Z0-9_]+)\[(\d+)\]$/', $seg, $m)) {
                $key = $m[1];
                $idx = (int)$m[2];
                if (!is_array($cur) || !array_key_exists($key, $cur) || !is_array($cur[$key])) {
                    return null;
                }
                $cur = $cur[$key][$idx] ?? null;
                continue;
            }

            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return null;
            }
            $cur = $cur[$seg];
        }

        return $cur;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
