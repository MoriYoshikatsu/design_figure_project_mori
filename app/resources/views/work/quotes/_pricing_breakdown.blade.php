@php
    $sectionTitle = isset($sectionTitle) ? trim((string)$sectionTitle) : '計算内訳';
    $pricingInput = is_array($pricingInput ?? null) ? $pricingInput : [];
    $pricingSteps = is_array($pricingSteps ?? null) ? $pricingSteps : [];
    $pricingOutput = is_array($pricingOutput ?? null) ? $pricingOutput : [];
    $context = is_array($context ?? null) ? $context : [];

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
    $toText = static function (mixed $value): string {
        if ($value === null) {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $text = trim((string)$value);
        return $text === '' ? '-' : $text;
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
        @endphp
        @if(count($inputScalarMap) > 0)
            <table class="quote-breakdown-kv">
                <tbody>
                @foreach($inputScalarMap as $key => $value)
                    <tr>
                        <th class="quote-breakdown-mono">{{ $key }}</th>
                        <td>{{ $toText($value) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="quote-breakdown-empty">入力値はありません。</div>
        @endif
        @if(is_array($pricingInput['labor_overrides'] ?? null))
            <details style="margin-top:6px;">
                <summary>labor_overrides</summary>
                <pre class="quote-breakdown-json">{{ json_encode($pricingInput['labor_overrides'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            </details>
        @endif
    </div>

    <div class="quote-breakdown-section">
        <h4>Step0（部材・作業費）</h4>
        @php
            $step0ScalarMap = $toScalarMap($step0);
        @endphp
        @if(count($step0ScalarMap) > 0)
            <table class="quote-breakdown-kv">
                <tbody>
                @foreach($step0ScalarMap as $key => $value)
                    <tr>
                        <th class="quote-breakdown-mono">{{ $key }}</th>
                        <td>{{ $toText($value) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="quote-breakdown-empty">Step0はありません。</div>
        @endif

        <div class="quote-breakdown-sub">
            <strong>部材内訳（parts_breakdown）</strong>
            @if(count($partsBreakdown) > 0)
                <table class="quote-breakdown-table" style="margin-top:6px;">
                    <thead>
                        <tr>
                            <th>sku_code</th>
                            <th>category</th>
                            <th>quantity</th>
                            <th>line_total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($partsBreakdown as $row)
                        @if(is_array($row))
                            <tr>
                                <td class="quote-breakdown-mono">{{ $toText($row['sku_code'] ?? null) }}</td>
                                <td>{{ $toText($row['category'] ?? null) }}</td>
                                <td>{{ $toText($row['quantity'] ?? null) }}</td>
                                <td>{{ $toText($row['line_total'] ?? null) }}</td>
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
            <strong>適用ルール（matched_labor_rules）</strong>
            @if(count($matchedLaborRules) > 0)
                <table class="quote-breakdown-table" style="margin-top:6px;">
                    <thead>
                        <tr>
                            <th>rule_code</th>
                            <th>rule_name</th>
                            <th>process_code</th>
                            <th>priority</th>
                            <th>always_apply</th>
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
            <strong>作業費内訳（labor_breakdown）</strong>
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
                                <tr><th>yield_rate_default</th><td>{{ $toText($processRow['yield_rate_default'] ?? null) }}</td></tr>
                                <tr><th>yield_rate_applied</th><td>{{ $toText($processRow['yield_rate_applied'] ?? null) }}</td></tr>
                                <tr><th>yield_source</th><td>{{ $toText($processRow['yield_source'] ?? null) }}</td></tr>
                                <tr><th>elements_total</th><td>{{ $toText($processRow['elements_total'] ?? null) }}</td></tr>
                                <tr><th>process_raw</th><td>{{ $toText($processRow['process_raw'] ?? null) }}</td></tr>
                                <tr><th>process_order_total</th><td>{{ $toText($processRow['process_order_total'] ?? null) }}</td></tr>
                                <tr><th>process_unit</th><td>{{ $toText($processRow['process_unit'] ?? null) }}</td></tr>
                            </tbody>
                        </table>

                        @php
                            $elements = is_array($processRow['elements'] ?? null) ? $processRow['elements'] : [];
                        @endphp
                        @if(count($elements) > 0)
                            <table class="quote-breakdown-table" style="margin-top:6px;">
                                <thead>
                                <tr>
                                    <th>element_code</th>
                                    <th>element_name</th>
                                    <th>work_minutes</th>
                                    <th>activity_coeff</th>
                                    <th>batch_size</th>
                                    <th>runs</th>
                                    <th>depreciation</th>
                                    <th>yield_applied</th>
                                    <th>yield_source</th>
                                    <th>element_base</th>
                                    <th>element_cost</th>
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
                                            <td>{{ $toText($element['depreciation_amount'] ?? null) }}</td>
                                            <td>{{ $toText($element['yield_rate_applied'] ?? null) }}</td>
                                            <td>{{ $toText($element['yield_source'] ?? null) }}</td>
                                            <td>{{ $toText($element['element_base'] ?? null) }}</td>
                                            <td>{{ $toText($element['element_cost'] ?? null) }}</td>
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
        <h4>Step1-6</h4>
        @if(count($stepRows) > 0)
            @foreach($stepRows as $stepRow)
                @php
                    $key = $stepRow['key'];
                    $value = is_array($stepRow['value'] ?? null) ? $stepRow['value'] : [];
                    $scalarMap = $toScalarMap($value);
                @endphp
                @if($key === 'step0')
                    @continue
                @endif
                <div class="quote-breakdown-sub">
                    <strong class="quote-breakdown-mono">{{ $key }}</strong>
                    @if(count($scalarMap) > 0)
                        <table class="quote-breakdown-kv" style="margin-top:6px;">
                            <tbody>
                            @foreach($scalarMap as $k => $v)
                                <tr>
                                    <th class="quote-breakdown-mono">{{ $k }}</th>
                                    <td>{{ $toText($v) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="quote-breakdown-empty" style="margin-top:4px;">データはありません。</div>
                    @endif
                </div>
            @endforeach
        @else
            <div class="quote-breakdown-empty">Stepデータはありません。</div>
        @endif
    </div>

    <div class="quote-breakdown-section">
        <h4>出力値</h4>
        @php
            $outputScalarMap = $toScalarMap($pricingOutput);
        @endphp
        @if(count($outputScalarMap) > 0)
            <table class="quote-breakdown-kv">
                <tbody>
                @foreach($outputScalarMap as $key => $value)
                    <tr>
                        <th class="quote-breakdown-mono">{{ $key }}</th>
                        <td>{{ $toText($value) }}</td>
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
            <pre class="quote-breakdown-json">{{ json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    <details class="quote-breakdown-section">
        <summary>生データ（JSON）</summary>
        <h4 style="margin-top:6px;">pricing_input</h4>
        <pre class="quote-breakdown-json">{{ json_encode($pricingInput, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        <h4 style="margin-top:6px;">pricing_steps</h4>
        <pre class="quote-breakdown-json">{{ json_encode($pricingSteps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        <h4 style="margin-top:6px;">pricing_output</h4>
        <pre class="quote-breakdown-json">{{ json_encode($pricingOutput, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
    </details>
</div>
