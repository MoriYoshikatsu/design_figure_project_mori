@php
    $sectionTitle = isset($sectionTitle) ? trim((string)$sectionTitle) : '計算内訳';
    $pricingInput = is_array($pricingInput ?? null) ? $pricingInput : [];
    $pricingSteps = is_array($pricingSteps ?? null) ? $pricingSteps : [];
    $pricingOutput = is_array($pricingOutput ?? null) ? $pricingOutput : [];
    $context = is_array($context ?? null) ? $context : [];
    $localized = (bool)($localized ?? false);
    $showRawJson = array_key_exists('showRawJson', get_defined_vars()) ? (bool)$showRawJson : !$localized;

    $keyLabelMap = [
        'order_qty' => '注文数量',
        'fixed_cost' => '固定性経費',
        'management_factor' => '管理費係数',
        'qty_discount_factor' => '数量ディスカウント係数',
        'customer_factor' => '顧客別仕切係数',
        'freight_amount' => '荷造運賃',
        'manual_discount_amount' => '任意値引き',
        'trade_scope' => '取引区分',
        'tax_rate' => '税率',
        'pricing_policy_id' => '価格計算プリセット',
        'rounding_currency' => '丸め通貨',
        'rounding_unit' => '丸め単位',
        'rounding_mode' => '丸め方式',
        'labor_overrides' => '作業費歩留まり上書き',
        'parts_unit_cost' => '部材単価',
        'labor_unit_cost' => '作業費単価',
        'labor_order_total' => '作業費合計',
        'variable_unit_cost' => '変動単価',
        'subtotal_raw' => '小計（元値）',
        'unit_price_raw' => '単価（元値）',
        'unit_price_rounded' => '単価（丸め後）',
        'recomputed_total' => '再計算合計',
        'adjusted_total' => '調整後合計',
        'tax_amount' => '税額',
        'grand_total' => '総合計',
        'parts_breakdown' => '部材内訳',
        'labor_breakdown' => '作業費内訳',
        'matched_labor_rules' => '適用ルール',
        'matched_process_codes' => '適用工程コード',
        'sku_code' => '品目コード',
        'category' => 'カテゴリ',
        'quantity' => '数量',
        'line_total' => '行合計',
        'rule_id' => 'ルールID',
        'rule_code' => 'ルールコード',
        'rule_name' => 'ルール名',
        'process_id' => '工程ID',
        'process_code' => '工程コード',
        'process_name' => '工程名',
        'priority' => '優先度',
        'always_apply' => '常時適用',
        'yield_rate_default' => '初期良品率',
        'yield_rate_applied' => '適用良品率',
        'yield_source' => '良品率算出元',
        'yield_input' => '良品率入力値',
        'elements_total' => '要素合計',
        'process_raw' => '工程原価',
        'process_order_total' => '工程合計',
        'process_unit' => '工程単価',
        'element_id' => '要素ID',
        'element_code' => '要素コード',
        'element_name' => '要素名',
        'work_minutes' => '作業時間（分）',
        'activity_coeff' => '活動係数',
        'batch_size' => '1回作業数量',
        'runs' => '実行回数',
        'depreciation_amount' => '減価償却費',
        'element_base' => '要素原価',
        'element_cost' => '要素費用',
        'run_id' => '履歴ID',
        'source_type' => '発生元種別',
        'source_id' => '発生元ID',
        'hourly_rate' => '時間チャージ',
    ];
    $valueLabelMap = [
        'DOMESTIC' => '国内',
        'OVERSEAS' => '海外',
        'ROUNDUP' => '切り上げ',
        'ROUNDDOWN' => '切り捨て',
        'ROUND_DOWN' => '切り捨て',
        'ROUND' => '四捨五入',
        'ROUND_HALF_UP' => '四捨五入',
        'HALF_UP' => '四捨五入',
        'JPY' => '日本円',
        'USD' => '米ドル',
        'EUR' => 'ユーロ',
        'ISSUE' => '見積発行',
        'EDIT_REQUEST_SUBMIT' => '変更申請送信',
        'EDIT_DIRECT_APPLY' => '即時反映',
        'EDIT_REQUEST_APPROVE' => '変更申請承認',
        'EDIT_REQUEST_REJECT' => '変更申請却下',
        'LEGACY_BASELINE' => '既存見積の基準化',
        'order_actual_input_ratio' => '注文数量÷実投入数',
        'yield_override' => '良品率上書き',
        'process_override' => '工程上書き',
        'element_override' => '要素上書き',
        'default' => '既定値',
        'true' => 'はい',
        'false' => 'いいえ',
        'quote' => '見積',
        'change_request' => '変更申請',
        'configurator' => 'コンフィギュレーター',
        'session' => 'セッション',
        'FIBER' => 'ファイバー',
        'TUBE' => 'チューブ',
        'SLEEVE' => 'スリーブ',
        'CONNECTOR' => 'コネクタ',
        'PROC' => '工程',
    ];
    $moneyKeys = array_flip([
        'fixed_cost',
        'freight_amount',
        'manual_discount_amount',
        'parts_unit_cost',
        'labor_unit_cost',
        'labor_order_total',
        'variable_unit_cost',
        'subtotal_raw',
        'unit_price_raw',
        'unit_price_rounded',
        'recomputed_total',
        'adjusted_total',
        'tax_amount',
        'grand_total',
        'line_total',
        'elements_total',
        'process_raw',
        'process_order_total',
        'process_unit',
        'depreciation_amount',
        'element_base',
        'element_cost',
        'hourly_rate',
        'subtotal',
        'tax',
        'total',
        'unit_price',
        'price_per_m',
    ]);
    $labelKey = static function (string $key) use ($keyLabelMap): string {
        $normalized = trim($key);
        if ($normalized === '') {
            return '項目';
        }
        if (preg_match('/^step([0-9]+)$/i', $normalized, $m) === 1) {
            return 'ステップ' . $m[1];
        }
        return $keyLabelMap[$normalized] ?? '項目';
    };

    $step0 = is_array($pricingSteps['step0'] ?? null) ? $pricingSteps['step0'] : [];
    $partsBreakdown = is_array($step0['parts_breakdown'] ?? null) ? $step0['parts_breakdown'] : [];
    $laborBreakdown = is_array($step0['labor_breakdown'] ?? null) ? $step0['labor_breakdown'] : [];
    $matchedLaborRules = is_array($step0['matched_labor_rules'] ?? null) ? $step0['matched_labor_rules'] : [];

    $stepRows = [];
    foreach ($pricingSteps as $stepKey => $stepValue) {
        if (!is_array($stepValue)) {
            continue;
        }
        $stepRows[] = [
            'key' => (string)$stepKey,
            'value' => $stepValue,
        ];
    }

    $toScalarMap = static function (array $row): array {
        $result = [];
        foreach ($row as $k => $v) {
            if (is_array($v) || is_object($v)) {
                continue;
            }
            $result[(string)$k] = $v;
        }
        return $result;
    };
    $toText = static function (mixed $value) use ($valueLabelMap): string {
        if ($value === null) {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'はい' : 'いいえ';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $text = trim((string)$value);
        if ($text === '') {
            return '-';
        }
        if (array_key_exists($text, $valueLabelMap)) {
            return (string)$valueLabelMap[$text];
        }
        $upper = strtoupper($text);
        if (array_key_exists($upper, $valueLabelMap)) {
            return (string)$valueLabelMap[$upper];
        }
        return $text;
    };
    $toDisplay = static function (?string $key, mixed $value) use ($moneyKeys, $toText): string {
        if ($key !== null && isset($moneyKeys[$key])) {
            return format_amount($value);
        }
        return $toText($value);
    };
    $toNumberText = static function (mixed $value) use ($toText): string {
        if (!is_numeric($value)) {
            return $toText($value);
        }
        $text = number_format((float)$value, 6, '.', '');
        $text = rtrim(rtrim($text, '0'), '.');
        return $text === '' ? '0' : $text;
    };
    $toDisplayRows = static function (array $scalarMap) use ($labelKey, $toDisplay, $toText): array {
        $rows = [];
        foreach ($scalarMap as $key => $value) {
            $normalized = (string)$key;
            if (in_array($normalized, ['rounding_currency', 'rounding_mode'], true)) {
                continue;
            }

            if ($normalized === 'rounding_unit') {
                $unitText = format_amount($value);
                $currencyText = $toText($scalarMap['rounding_currency'] ?? null);
                $combined = $currencyText !== '-'
                    ? ($unitText . ' ' . $currencyText)
                    : ($unitText . '（丸め単位金額）');
                $rows[] = [
                    'label' => '丸め単位',
                    'value' => $combined,
                ];
                continue;
            }

            $rows[] = [
                'label' => $labelKey($normalized),
                'value' => $toDisplay($normalized, $value),
            ];
        }

        return $rows;
    };
    $stepFormulaLines = static function (string $stepKey, array $stepValue) use ($pricingInput, $pricingSteps, $step0, $toText, $toNumberText): array {
        $stepKeyNormalized = strtolower(trim($stepKey));
        $orderQty = max(1, (int)($pricingInput['order_qty'] ?? 1));
        $fmtQty = number_format($orderQty);
        $lines = [];

        $fmtMoney = static fn (mixed $v): string => format_amount($v);

        if ($stepKeyNormalized === 'step0') {
            $partsUnitCost = $stepValue['parts_unit_cost'] ?? null;
            $laborUnitCost = $stepValue['labor_unit_cost'] ?? null;
            $laborOrderTotal = $stepValue['labor_order_total'] ?? null;
            $variableUnitCost = $stepValue['variable_unit_cost'] ?? null;
            $lines[] = '部材単価 = 部材内訳（非PROC）の行合計の合算';
            $lines[] = '作業費単価 = 作業費合計 ÷ 注文数量 = ' . $fmtMoney($laborOrderTotal) . ' ÷ ' . $fmtQty . ' = ' . $fmtMoney($laborUnitCost);
            $lines[] = '変動単価 = 部材単価 + 作業費単価 = ' . $fmtMoney($partsUnitCost) . ' + ' . $fmtMoney($laborUnitCost) . ' = ' . $fmtMoney($variableUnitCost);
            return $lines;
        }

        if ($stepKeyNormalized === 'step1') {
            $fixedCost = $pricingInput['fixed_cost'] ?? null;
            $variableUnitCost = $step0['variable_unit_cost'] ?? null;
            $managementFactor = $pricingInput['management_factor'] ?? null;
            $qtyDiscountFactor = $pricingInput['qty_discount_factor'] ?? null;
            $customerFactor = $pricingInput['customer_factor'] ?? null;
            $subtotalRaw = $stepValue['subtotal_raw'] ?? null;
            $lines[] = '小計（元値） = (固定性経費 + 変動単価 × 注文数量) × 管理費係数 × 数量ディスカウント係数 × 顧客別仕切係数';
            $lines[] = '= (' . $fmtMoney($fixedCost) . ' + ' . $fmtMoney($variableUnitCost) . ' × ' . $fmtQty . ') × ' . $toNumberText($managementFactor) . ' × ' . $toNumberText($qtyDiscountFactor) . ' × ' . $toNumberText($customerFactor) . ' = ' . $fmtMoney($subtotalRaw);
            return $lines;
        }

        if ($stepKeyNormalized === 'step2') {
            $subtotalRaw = $pricingSteps['step1']['subtotal_raw'] ?? null;
            $unitPriceRaw = $stepValue['unit_price_raw'] ?? null;
            $unitPriceRounded = $stepValue['unit_price_rounded'] ?? null;
            $roundingUnit = $stepValue['rounding_unit'] ?? ($pricingInput['rounding_unit'] ?? null);
            $roundingCurrency = $stepValue['rounding_currency'] ?? ($pricingInput['rounding_currency'] ?? null);
            $roundingCurrencyText = $toText($roundingCurrency);
            $roundingPair = $fmtMoney($roundingUnit) . ' ' . ($roundingCurrencyText !== '-' ? $roundingCurrencyText : '');
            $lines[] = '単価（元値） = 小計（元値） ÷ 注文数量 = ' . $fmtMoney($subtotalRaw) . ' ÷ ' . $fmtQty . ' = ' . $fmtMoney($unitPriceRaw);
            $lines[] = '単価（丸め後） = 単価（元値）を丸め単位で丸める = ' . $fmtMoney($unitPriceRaw) . ' を ' . $roundingPair . ' で丸め = ' . $fmtMoney($unitPriceRounded);
            return $lines;
        }

        if ($stepKeyNormalized === 'step3') {
            $unitPriceRounded = $pricingSteps['step2']['unit_price_rounded'] ?? null;
            $recomputedTotal = $stepValue['recomputed_total'] ?? null;
            $lines[] = '再計算合計 = 単価（丸め後） × 注文数量 = ' . $fmtMoney($unitPriceRounded) . ' × ' . $fmtQty . ' = ' . $fmtMoney($recomputedTotal);
            return $lines;
        }

        if ($stepKeyNormalized === 'step4') {
            $recomputedTotal = $pricingSteps['step3']['recomputed_total'] ?? null;
            $freightAmount = $stepValue['freight_amount'] ?? null;
            $manualDiscountAmount = $stepValue['manual_discount_amount'] ?? null;
            $adjustedTotal = $stepValue['adjusted_total'] ?? null;
            $lines[] = '調整後合計 = 再計算合計 + 荷造運賃 + 任意値引き';
            $lines[] = '= ' . $fmtMoney($recomputedTotal) . ' + ' . $fmtMoney($freightAmount) . ' + ' . $fmtMoney($manualDiscountAmount) . ' = ' . $fmtMoney($adjustedTotal);
            return $lines;
        }

        if ($stepKeyNormalized === 'step5') {
            $adjustedTotal = $pricingSteps['step4']['adjusted_total'] ?? null;
            $taxRate = $stepValue['tax_rate'] ?? null;
            $taxAmount = $stepValue['tax_amount'] ?? null;
            $lines[] = '税額 = 調整後合計 × 税率 = ' . $fmtMoney($adjustedTotal) . ' × ' . $toNumberText($taxRate) . ' = ' . $fmtMoney($taxAmount);
            return $lines;
        }

        if ($stepKeyNormalized === 'step6') {
            $adjustedTotal = $pricingSteps['step4']['adjusted_total'] ?? null;
            $taxAmount = $pricingSteps['step5']['tax_amount'] ?? null;
            $grandTotal = $stepValue['grand_total'] ?? null;
            $lines[] = '総合計 = 調整後合計 + 税額 = ' . $fmtMoney($adjustedTotal) . ' + ' . $fmtMoney($taxAmount) . ' = ' . $fmtMoney($grandTotal);
            return $lines;
        }

        return $lines;
    };
@endphp

@once
    <style>
        .quote-breakdown-wrap {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            padding: 10px;
            margin-top: 10px;
        }
        .quote-breakdown-wrap h3 {
            margin: 0 0 8px;
        }
        .quote-breakdown-section {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
            padding: 8px;
            margin-top: 8px;
        }
        .quote-breakdown-section > h4 {
            margin: 0 0 6px;
        }
        .quote-breakdown-sub {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            padding: 6px;
            margin-top: 6px;
        }
        .quote-breakdown-kv {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .quote-breakdown-kv th,
        .quote-breakdown-kv td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            font-size: 12px;
            vertical-align: top;
        }
        .quote-breakdown-kv th {
            width: 220px;
            background: #f3f4f6;
            font-weight: 600;
        }
        .quote-breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        .quote-breakdown-table th,
        .quote-breakdown-table td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            font-size: 12px;
            vertical-align: top;
        }
        .quote-breakdown-table th {
            background: #f3f4f6;
            font-weight: 600;
        }
        .quote-breakdown-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
        }
        .quote-breakdown-empty {
            color: #6b7280;
            font-size: 12px;
        }
        .quote-breakdown-json {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            margin: 4px 0 0;
            font-size: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            padding: 6px;
        }
    </style>
@endonce

<div class="quote-breakdown-wrap">
    @if($sectionTitle !== '')
        <h3>{{ $sectionTitle }}</h3>
    @endif

    <div class="quote-breakdown-section">
        <h4>入力値</h4>
        @php
            $inputScalarMap = $toScalarMap($pricingInput);
            $inputDisplayRows = $toDisplayRows($inputScalarMap);
        @endphp
        @if(count($inputDisplayRows) > 0)
            <table class="quote-breakdown-kv">
                <tbody>
                @foreach($inputDisplayRows as $row)
                    <tr>
                        <th>{{ $row['label'] }}</th>
                        <td>{{ $row['value'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="quote-breakdown-empty">入力値はありません。</div>
        @endif
        @if(is_array($pricingInput['labor_overrides'] ?? null))
            <details style="margin-top:6px;">
                <summary>作業費歩留まり上書き</summary>
                <pre class="quote-breakdown-json">{{ json_encode($pricingInput['labor_overrides'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            </details>
        @endif
    </div>

    <div class="quote-breakdown-section">
        <h4>ステップ0（部材・作業費）</h4>
        @php
            $step0ScalarMap = $toScalarMap($step0);
            $step0DisplayRows = $toDisplayRows($step0ScalarMap);
            $step0Formula = $stepFormulaLines('step0', $step0);
        @endphp
        @if(count($step0DisplayRows) > 0)
            <table class="quote-breakdown-kv">
                <tbody>
                @foreach($step0DisplayRows as $row)
                    <tr>
                        <th>{{ $row['label'] }}</th>
                        <td>{{ $row['value'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="quote-breakdown-empty">ステップ0はありません。</div>
        @endif

        @if(count($step0Formula) > 0)
            <div class="quote-breakdown-sub">
                <ul style="margin:6px 0 0 18px; padding:0;">
                    @foreach($step0Formula as $line)
                        <li class="quote-breakdown-mono">{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="quote-breakdown-sub">
            <strong>部材内訳</strong>
            @if(count($partsBreakdown) > 0)
                <table class="quote-breakdown-table" style="margin-top:6px;">
                    <thead>
                        <tr>
                            <th>品目コード</th>
                            <th>カテゴリ</th>
                            <th>数量</th>
                            <th>行合計</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($partsBreakdown as $row)
                        @if(is_array($row))
                            <tr>
                                <td class="quote-breakdown-mono">{{ $toText($row['sku_code'] ?? null) }}</td>
                                <td>{{ $toText($row['category'] ?? null) }}</td>
                                <td>{{ $toText($row['quantity'] ?? null) }}</td>
                                <td>{{ $toDisplay('line_total', $row['line_total'] ?? null) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="quote-breakdown-empty" style="margin-top:4px;">部材内訳はありません。</div>
            @endif
        </div>

        <div class="quote-breakdown-sub">
            <strong>適用ルール</strong>
            @if(count($matchedLaborRules) > 0)
                <table class="quote-breakdown-table" style="margin-top:6px;">
                    <thead>
                        <tr>
                            <th>ルールコード</th>
                            <th>ルール名</th>
                            <th>工程コード</th>
                            <th>優先度</th>
                            <th>常時適用</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($matchedLaborRules as $rule)
                        @if(is_array($rule))
                            <tr>
                                <td class="quote-breakdown-mono">{{ $toText($rule['rule_code'] ?? null) }}</td>
                                <td>{{ $toText($rule['rule_name'] ?? null) }}</td>
                                <td class="quote-breakdown-mono">{{ $toText($rule['process_code'] ?? null) }}</td>
                                <td>{{ $toText($rule['priority'] ?? null) }}</td>
                                <td>{{ $toText($rule['always_apply'] ?? null) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            @else
                <div class="quote-breakdown-empty" style="margin-top:4px;">適用ルールはありません。</div>
            @endif
        </div>

        <div class="quote-breakdown-sub">
            <strong>作業費内訳</strong>
            @if(count($laborBreakdown) > 0)
                @foreach($laborBreakdown as $processRow)
                    @if(!is_array($processRow))
                        @continue
                    @endif
                    <div class="quote-breakdown-sub" style="margin-top:6px;">
                        <div>
                            <strong>{{ $toText($processRow['process_name'] ?? null) }}</strong>
                            <span class="quote-breakdown-mono">({{ $toText($processRow['process_code'] ?? null) }})</span>
                        </div>
                        <table class="quote-breakdown-kv" style="margin-top:6px;">
                            <tbody>
                                <tr><th>初期良品率</th><td>{{ $toText($processRow['yield_rate_default'] ?? null) }}</td></tr>
                                <tr><th>適用良品率</th><td>{{ $toText($processRow['yield_rate_applied'] ?? null) }}</td></tr>
                                <tr><th>良品率算出元</th><td>{{ $toText($processRow['yield_source'] ?? null) }}</td></tr>
                                <tr><th>要素合計</th><td>{{ $toDisplay('elements_total', $processRow['elements_total'] ?? null) }}</td></tr>
                                <tr><th>工程原価</th><td>{{ $toDisplay('process_raw', $processRow['process_raw'] ?? null) }}</td></tr>
                                <tr><th>工程合計</th><td>{{ $toDisplay('process_order_total', $processRow['process_order_total'] ?? null) }}</td></tr>
                                <tr><th>工程単価</th><td>{{ $toDisplay('process_unit', $processRow['process_unit'] ?? null) }}</td></tr>
                            </tbody>
                        </table>

                        @php
                            $elements = is_array($processRow['elements'] ?? null) ? $processRow['elements'] : [];
                        @endphp
                        @if(count($elements) > 0)
                            <table class="quote-breakdown-table" style="margin-top:6px;">
                                <thead>
                                <tr>
                                    <th>要素コード</th>
                                    <th>要素名</th>
                                    <th>作業時間（分）</th>
                                    <th>活動係数</th>
                                    <th>1回作業数量</th>
                                    <th>実行回数</th>
                                    <th>減価償却費</th>
                                    <th>適用良品率</th>
                                    <th>良品率算出元</th>
                                    <th>要素原価</th>
                                    <th>要素費用</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($elements as $element)
                                    @if(is_array($element))
                                        <tr>
                                            <td class="quote-breakdown-mono">{{ $toText($element['element_code'] ?? null) }}</td>
                                            <td>{{ $toText($element['element_name'] ?? null) }}</td>
                                            <td>{{ $toText($element['work_minutes'] ?? null) }}</td>
                                            <td>{{ $toText($element['activity_coeff'] ?? null) }}</td>
                                            <td>{{ $toText($element['batch_size'] ?? null) }}</td>
                                            <td>{{ $toText($element['runs'] ?? null) }}</td>
                                            <td>{{ $toDisplay('depreciation_amount', $element['depreciation_amount'] ?? null) }}</td>
                                            <td>{{ $toText($element['yield_rate_applied'] ?? null) }}</td>
                                            <td>{{ $toText($element['yield_source'] ?? null) }}</td>
                                            <td>{{ $toDisplay('element_base', $element['element_base'] ?? null) }}</td>
                                            <td>{{ $toDisplay('element_cost', $element['element_cost'] ?? null) }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="quote-breakdown-empty" style="margin-top:4px;">要素内訳はありません。</div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="quote-breakdown-empty" style="margin-top:4px;">作業費内訳はありません。</div>
            @endif
        </div>
    </div>

    <div class="quote-breakdown-section">
        <h4>ステップ1〜6</h4>
        @if(count($stepRows) > 0)
            @foreach($stepRows as $stepRow)
                @php
                    $key = $stepRow['key'];
                    $value = is_array($stepRow['value'] ?? null) ? $stepRow['value'] : [];
                    $scalarMap = $toScalarMap($value);
                    $displayRows = $toDisplayRows($scalarMap);
                    $formulaLines = $stepFormulaLines((string)$key, $value);
                @endphp
                @if($key === 'step0')
                    @continue
                @endif
                <div class="quote-breakdown-sub">
                    <strong>{{ $labelKey((string)$key) }}</strong>
                    @if(count($displayRows) > 0)
                        <table class="quote-breakdown-kv" style="margin-top:6px;">
                            <tbody>
                            @foreach($displayRows as $row)
                                <tr>
                                    <th>{{ $row['label'] }}</th>
                                    <td>{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="quote-breakdown-empty" style="margin-top:4px;">データはありません。</div>
                    @endif

                    @if(count($formulaLines) > 0)
                        <div class="quote-breakdown-sub" style="margin-top:6px;">
                            <ul style="margin:6px 0 0 18px; padding:0;">
                                @foreach($formulaLines as $line)
                                    <li class="quote-breakdown-mono">{{ $line }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            <div class="quote-breakdown-empty">ステップデータはありません。</div>
        @endif
    </div>

    <div class="quote-breakdown-section">
        <h4>出力値</h4>
        @php
            $outputScalarMap = $toScalarMap($pricingOutput);
            $outputDisplayRows = $toDisplayRows($outputScalarMap);
        @endphp
        @if(count($outputDisplayRows) > 0)
            <table class="quote-breakdown-kv">
                <tbody>
                @foreach($outputDisplayRows as $row)
                    <tr>
                        <th>{{ $row['label'] }}</th>
                        <td>{{ $row['value'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="quote-breakdown-empty">出力値はありません。</div>
        @endif
    </div>

    @if(count($context) > 0)
        <div class="quote-breakdown-section">
            <h4>実行コンテキスト</h4>
            @if($localized)
                @php
                    $contextScalarMap = $toScalarMap($context);
                    $contextDisplayRows = $toDisplayRows($contextScalarMap);
                @endphp
                @if(count($contextDisplayRows) > 0)
                    <table class="quote-breakdown-kv">
                        <tbody>
                        @foreach($contextDisplayRows as $row)
                            <tr>
                                <th>{{ $row['label'] }}</th>
                                <td>{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="quote-breakdown-empty">実行コンテキストはありません。</div>
                @endif
            @else
                <pre class="quote-breakdown-json">{{ json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>
    @endif

    @if($showRawJson)
        <details class="quote-breakdown-section">
            <summary>生データ（JSON）</summary>
            <h4 style="margin-top:6px;">入力JSON</h4>
            <pre class="quote-breakdown-json">{{ json_encode($pricingInput, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            <h4 style="margin-top:6px;">ステップJSON</h4>
            <pre class="quote-breakdown-json">{{ json_encode($pricingSteps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            <h4 style="margin-top:6px;">出力JSON</h4>
            <pre class="quote-breakdown-json">{{ json_encode($pricingOutput, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        </details>
    @endif
</div>
