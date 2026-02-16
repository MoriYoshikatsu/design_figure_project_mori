@php
    $panelTitle = $panelTitle ?? 'スナップショット';
    $pdfUrl = $pdfUrl ?? null;
    $pdfLabel = $pdfLabel ?? 'PDFダウンロード';
    $summaryItems = is_array($summaryItems ?? null) ? $summaryItems : [];
    $includeAutoSummary = (bool)($includeAutoSummary ?? true);
    $showDetails = (bool)($showDetails ?? true);
    $detailsInToggle = (bool)($detailsInToggle ?? true);
    $detailsSummaryLabel = (string)($detailsSummaryLabel ?? '詳細（エラー・構成価格表・JSON）');
    $showErrorTable = (bool)($showErrorTable ?? true);
    $showConfigPriceTable = (bool)($showConfigPriceTable ?? true);
    $showSummary = (bool)($showSummary ?? true);
    $summaryTitle = (string)($summaryTitle ?? '概要');
    $errorTableLabel = (string)($errorTableLabel ?? '検証エラー');
    $summaryUseTableLayout = (bool)($summaryUseTableLayout ?? false);
    $showCreatorColumns = (bool)($showCreatorColumns ?? false);
    $creatorAccountDisplayName = trim((string)($creatorAccountDisplayName ?? ''));
    $creatorEmail = trim((string)($creatorEmail ?? ''));
    $creatorAssigneeName = trim((string)($creatorAssigneeName ?? ''));
    $creatorAccountDisplayText = $creatorAccountDisplayName !== '' ? $creatorAccountDisplayName : '-';
    $creatorEmailText = $creatorEmail !== '' ? $creatorEmail : '-';
    $creatorAssigneeText = $creatorAssigneeName !== '' ? $creatorAssigneeName : '-';
    $showSourcePathColumn = (bool)($showSourcePathColumn ?? true);
    $showQuantityColumn = (bool)($showQuantityColumn ?? true);
    $showPriceColumns = (bool)($showPriceColumns ?? true);
    $showSkuOnlyWhenPriced = (bool)($showSkuOnlyWhenPriced ?? false);
    $configTableLabel = (string)($configTableLabel ?? '構成価格表');
    $showJsonSection = (bool)($showJsonSection ?? true);
    $showMemoCard = (bool)($showMemoCard ?? false);
    $memoValue = (string)($memoValue ?? '');
    $memoLabel = (string)($memoLabel ?? 'メモ');
    $memoUpdateUrl = $memoUpdateUrl ?? null;
    $memoFieldName = (string)($memoFieldName ?? 'memo');
    $memoRows = max(2, (int)($memoRows ?? 3));
    $memoFixedHeightPx = max(32, (int)($memoFixedHeightPx ?? 40));
    $memoButtonLabel = (string)($memoButtonLabel ?? 'メモ保存');
    $memoHttpMethod = strtoupper((string)($memoHttpMethod ?? 'PUT'));
    $memoReadonly = (bool)($memoReadonly ?? false);
    // summaryLayoutMode:
    // - fit: 指定列数summaryColumnsに収めることを優先。収まらない時のみ次行へ折り返し
    // - row: 固定列グリッド（summaryColumns）
    // - column: 固定行グリッド（summaryRows）
    // 互換: summaryFlow=row/column が渡された場合は優先
    $summaryFlowInput = $summaryFlow ?? null;
    $summaryLayoutMode = (string)($summaryLayoutMode ?? '');
    if ($summaryLayoutMode === '') {
        if ($summaryFlowInput === 'column') {
            $summaryLayoutMode = 'column';
        } elseif ($summaryFlowInput === 'row') {
            $summaryLayoutMode = 'row';
        } else {
            $summaryLayoutMode = 'fit';                 
        }
    }
    if (!in_array($summaryLayoutMode, ['fit', 'row', 'column'], true)) {
        $summaryLayoutMode = 'fit';
    }
    $summaryColumnsInput = $summaryColumns ?? null;
    $summaryRowsInput = $summaryRows ?? null;
    $summaryColumns = max(1, (int)($summaryColumnsInput ?? 10));         //fit/row で目標列数
    $summaryRows = max(1, (int)($summaryRowsInput ?? 2));               //column 用（fit では列数未指定時の補助）
    $summaryMinCardWidth = max(60, (int)($summaryMinCardWidth ?? 120));  //折り返し判定に使う最小カード幅
    $summaryGapPx = max(0, (int)($summaryGapPx ?? 8));                  //カード間ギャップ
    $svg = (string)($svg ?? '');

    $config = is_array($config ?? null) ? $config : [];
    $derived = is_array($derived ?? null) ? $derived : [];
    $errors = is_array($errors ?? null) ? $errors : [];
    $snapshot = is_array($snapshot ?? null) ? $snapshot : [];
    $skuNameByCode = is_array($derived['skuNameByCode'] ?? null) ? $derived['skuNameByCode'] : [];
    if (empty($skuNameByCode)) {
        $skuNameByCode = \Illuminate\Support\Facades\DB::table('skus')->pluck('name', 'sku_code')->all();
    }
    $toSkuName = static function (?string $code) use ($skuNameByCode): string {
        if ($code === null || $code === '') {
            return '';
        }
        return (string)($skuNameByCode[$code] ?? '');
    };

    $sleeves = is_array($config['sleeves'] ?? null) ? $config['sleeves'] : [];
    $fibers = is_array($config['fibers'] ?? null) ? $config['fibers'] : [];
    $tubes = is_array($config['tubes'] ?? null) ? $config['tubes'] : [];
    $connectors = is_array($config['connectors'] ?? null) ? $config['connectors'] : [];

    $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
    $bom = is_array($snapshot['bom'] ?? null) ? $snapshot['bom'] : [];
    $pricing = is_array($snapshot['pricing'] ?? null) ? $snapshot['pricing'] : [];

    $pricingBySort = [];
    foreach ($pricing as $p) {
        if (!is_array($p)) continue;
        $pricingBySort[(int)($p['sort_order'] ?? 0)] = $p;
    }

    $bomByPath = [];
    $bomFirstBySku = [];
    $mfdBom = null;
    foreach ($bom as $b) {
        if (!is_array($b)) continue;
        $sortKey = (int)($b['sort_order'] ?? 0);
        $path = (string)($b['source_path'] ?? '');
        $skuCode = (string)($b['sku_code'] ?? '');
        $priceRow = $pricingBySort[$sortKey] ?? [];
        $row = [
            'sku_code' => $skuCode,
            'quantity' => $b['quantity'] ?? '',
            'source_path' => $path,
            'unit_price' => $priceRow['unit_price'] ?? '',
            'line_total' => $priceRow['line_total'] ?? '',
        ];
        if ($skuCode === 'PROC_MFD_CONVERSION' && $mfdBom === null) {
            $mfdBom = $row;
        }
        if ($path !== '') {
            $bomByPath[$path] = $row;
        }
        if ($skuCode !== '' && !array_key_exists($skuCode, $bomFirstBySku)) {
            $bomFirstBySku[$skuCode] = $row;
        }
    }

    $mfdCount = (int)($config['mfdCount'] ?? 0);
    $mfdQty = is_numeric($mfdBom['quantity'] ?? null) ? (float)$mfdBom['quantity'] : 0.0;
    $mfdLineTotal = is_numeric($mfdBom['line_total'] ?? null) ? (float)$mfdBom['line_total'] : 0.0;
    $mfdLineEach = $mfdQty > 0 ? ($mfdLineTotal / $mfdQty) : 0.0;

    $rows = [];
    for ($i = 0; $i < $mfdCount; $i++) {
        $mfdSkuCode = (string)($mfdBom['sku_code'] ?? '');
        $rows[] = [
            'type' => 'MFD変換',
            'index' => '['.$i.']',
            'sku_code' => $mfdSkuCode,
            'priced_sku_code' => $mfdSkuCode,
            'sku_name' => $toSkuName($mfdSkuCode),
            'priced_sku_name' => $toSkuName($mfdSkuCode),
            'source_path' => $mfdBom['source_path'] ?? '',
            'range' => '-',
            'tolerance' => '-',
            'quantity' => '1',
            'unit_price' => $mfdBom['unit_price'] ?? '',
            'line_total' => $mfdQty > 0 ? number_format($mfdLineEach, 2, '.', '') : '',
        ];
    }

    foreach ($sleeves as $i => $s) {
        $path = '$.sleeves['.$i.']';
        $r = $bomByPath[$path] ?? null;
        $selectedSku = (string)($s['skuCode'] ?? '');
        $pricedSku = (string)($r['sku_code'] ?? '');
        $rows[] = [
            'type' => 'スリーブ(MFD)',
            'index' => '['.$i.']',
            'sku_code' => $selectedSku,
            'priced_sku_code' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $pricedSku : '',
            'sku_name' => $toSkuName($selectedSku),
            'priced_sku_name' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $toSkuName($pricedSku) : '',
            'source_path' => $r['source_path'] ?? $path,
            'range' => '-',
            'tolerance' => '-',
            'quantity' => $r['quantity'] ?? '',
            'unit_price' => $r['unit_price'] ?? '',
            'line_total' => $r['line_total'] ?? '',
        ];
    }

    foreach ($fibers as $i => $f) {
        $path = '$.fibers['.$i.']';
        $r = $bomByPath[$path] ?? null;
        $selectedSku = (string)($f['skuCode'] ?? '');
        $pricedSku = (string)($r['sku_code'] ?? '');
        $rows[] = [
            'type' => 'ファイバ(F)',
            'index' => '['.$i.']',
            'sku_code' => $selectedSku,
            'priced_sku_code' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $pricedSku : '',
            'sku_name' => $toSkuName($selectedSku),
            'priced_sku_name' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $toSkuName($pricedSku) : '',
            'source_path' => $r['source_path'] ?? $path,
            'range' => isset($f['lengthMm']) ? ($f['lengthMm'].'mm') : '',
            'tolerance' => isset($f['toleranceMm']) ? ('±'.$f['toleranceMm'].'mm') : '',
            'quantity' => $r['quantity'] ?? '',
            'unit_price' => $r['unit_price'] ?? '',
            'line_total' => $r['line_total'] ?? '',
        ];
    }

    foreach ($tubes as $i => $t) {
        $path = '$.tubes['.$i.']';
        $r = $bomByPath[$path] ?? null;
        $selectedSku = (string)($t['skuCode'] ?? '');
        $pricedSku = (string)($r['sku_code'] ?? '');
        $sf = $t['startFiberIndex'] ?? $t['targetFiberIndex'] ?? '';
        $ef = $t['endFiberIndex'] ?? $t['targetFiberIndex'] ?? '';
        $so = $t['startOffsetMm'] ?? '';
        $eo = $t['endOffsetMm'] ?? '';
        $range = ($sf !== '' || $ef !== '') ? ('F'.$sf.'+'.$so.' → F'.$ef.'+'.$eo) : '';
        $rows[] = [
            'type' => 'チューブ(T)',
            'index' => '['.$i.']',
            'sku_code' => $selectedSku,
            'priced_sku_code' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $pricedSku : '',
            'sku_name' => $toSkuName($selectedSku),
            'priced_sku_name' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $toSkuName($pricedSku) : '',
            'source_path' => $r['source_path'] ?? $path,
            'range' => $range,
            'tolerance' => isset($t['toleranceMm']) ? ('±'.$t['toleranceMm'].'mm') : '',
            'quantity' => $r['quantity'] ?? '',
            'unit_price' => $r['unit_price'] ?? '',
            'line_total' => $r['line_total'] ?? '',
        ];
    }

    $connectorMode = (string)($connectors['mode'] ?? '');
    $showLeftConnector = in_array($connectorMode, ['left', 'both'], true);
    $showRightConnector = in_array($connectorMode, ['right', 'both'], true);

    $leftSku = (string)($connectors['leftSkuCode'] ?? '');
    $leftRow = $leftSku !== '' ? ($bomByPath['$.connectors.leftSkuCode'] ?? null) : null;
    $leftPricedSku = (string)($leftRow['sku_code'] ?? '');
    if ($showLeftConnector) {
        $rows[] = [
            'type' => 'コネクタ',
            'index' => '左端',
            'sku_code' => $leftSku,
            'priced_sku_code' => ($leftSku !== '' && $leftPricedSku === $leftSku) ? $leftPricedSku : '',
            'sku_name' => $toSkuName($leftSku),
            'priced_sku_name' => ($leftSku !== '' && $leftPricedSku === $leftSku) ? $toSkuName($leftPricedSku) : '',
            'source_path' => $leftRow['source_path'] ?? '',
            'range' => '-',
            'tolerance' => '-',
            'quantity' => $leftRow['quantity'] ?? '',
            'unit_price' => $leftRow['unit_price'] ?? '',
            'line_total' => $leftRow['line_total'] ?? '',
        ];
    }

    $rightSku = (string)($connectors['rightSkuCode'] ?? '');
    $rightRow = $rightSku !== '' ? ($bomByPath['$.connectors.rightSkuCode'] ?? null) : null;
    $rightPricedSku = (string)($rightRow['sku_code'] ?? '');
    if ($showRightConnector) {
        $rows[] = [
            'type' => 'コネクタ',
            'index' => '右端',
            'sku_code' => $rightSku,
            'priced_sku_code' => ($rightSku !== '' && $rightPricedSku === $rightSku) ? $rightPricedSku : '',
            'sku_name' => $toSkuName($rightSku),
            'priced_sku_name' => ($rightSku !== '' && $rightPricedSku === $rightSku) ? $toSkuName($rightPricedSku) : '',
            'source_path' => $rightRow['source_path'] ?? '',
            'range' => '-',
            'tolerance' => '-',
            'quantity' => $rightRow['quantity'] ?? '',
            'unit_price' => $rightRow['unit_price'] ?? '',
            'line_total' => $rightRow['line_total'] ?? '',
        ];
    }

    if (count($rows) === 0) {
        $rows[] = [
            'type' => '-',
            'index' => '-',
            'sku_code' => '',
            'priced_sku_code' => '',
            'sku_name' => '',
            'priced_sku_name' => '',
            'source_path' => '',
            'range' => '',
            'tolerance' => '',
            'quantity' => '',
            'unit_price' => '',
            'line_total' => '',
        ];
    }

    $summaryAuto = [
        ['label' => 'ルールテンプレ', 'value' => $snapshot['template_version_id'] ?? ''],
        ['label' => '納品物価格表', 'value' => $snapshot['price_book_id'] ?? ''],
        // ['label' => 'MFD数', 'value' => $config['mfdCount'] ?? ''],
        // ['label' => 'チューブ数', 'value' => $config['tubeCount'] ?? ''],
        // ['label' => 'エラー件数', 'value' => is_array($errors) ? count($errors) : 0],
        // ['label' => 'BOM件数', 'value' => count($bom)],
        // ['label' => '価格内訳件数', 'value' => count($pricing)],
        ['label' => '小計', 'value' => $totals['subtotal'] ?? ''],
        ['label' => '税', 'value' => $totals['tax'] ?? ''],
        ['label' => '合計', 'value' => $totals['total'] ?? ''],
    ];
    $summary = $includeAutoSummary ? array_merge($summaryItems, $summaryAuto) : $summaryItems;

    if ($summaryLayoutMode === 'fit' && $summaryColumnsInput === null && $summaryRowsInput !== null) {
        $summaryColumns = max(1, (int)ceil(max(1, count($summary)) / $summaryRows));
    }
    $summaryTableColumns = max(1, (int)($summaryTableColumns ?? $summaryColumns));

    $summaryGridStyle = '';
    $summaryCardBaseStyle = 'padding:8px; background:#fff; border:1px solid #e5e7eb; border-radius:6px;';
    $summaryCardStyle = $summaryCardBaseStyle;
    if ($summaryLayoutMode === 'column') {
        $summaryGridStyle = 'display:grid; gap:'.$summaryGapPx.'px;';
        $summaryGridStyle .= ' grid-auto-flow:column;';
        $summaryGridStyle .= ' grid-template-rows:repeat('.$summaryRows.', minmax(80px, auto));';
        $summaryGridStyle .= ' grid-auto-columns:minmax(140px, 1fr);';
    } elseif ($summaryLayoutMode === 'row') {
        $summaryGridStyle = 'display:grid; gap:'.$summaryGapPx.'px;';
        $summaryGridStyle .= ' grid-template-columns:repeat('.$summaryColumns.', minmax(140px, 1fr));';
    } else {
        // 指定列数に収めることを優先し、幅が足りない時のみ折り返し
        $summaryGapTotal = $summaryGapPx * max(0, $summaryColumns - 1);
        $summaryBasis = 'calc((100% - '.$summaryGapTotal.'px) / '.$summaryColumns.')';
        $summaryGridStyle = 'display:flex; flex-wrap:wrap; gap:'.$summaryGapPx.'px; align-items:stretch; overflow:hidden;';
        $summaryCardStyle .= ' flex:1 1 '.$summaryBasis.';';
        $summaryCardStyle .= ' max-width:'.$summaryBasis.';';
        $summaryCardStyle .= ' min-width:min(100%, '.$summaryMinCardWidth.'px);';
    }
    $memoCardStyle = $summaryCardStyle;
    if ($summaryLayoutMode === 'fit') {
        // 余白がある場合はメモカードを横方向に優先して広げる
        $memoCardStyle .= ' flex-grow:999; max-width:none;';
    } elseif ($summaryLayoutMode === 'row') {
        // 固定列レイアウト時はメモカードを1行いっぱいに使う
        $memoCardStyle .= ' grid-column:1 / -1;';
    }
    $memoFieldStyle = 'width:100%; height:'.$memoFixedHeightPx.'px; min-height:'.$memoFixedHeightPx.'px; max-height:'.$memoFixedHeightPx.'px; box-sizing:border-box; overflow:auto;';

    $snapshotJsonText = isset($snapshotJson) ? (string)$snapshotJson : json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $configJsonText = isset($configJson) ? (string)$configJson : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $derivedJsonText = isset($derivedJson) ? (string)$derivedJson : json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $errorsJsonText = isset($errorsJson) ? (string)$errorsJson : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
@endphp

<div style="margin:12px 0;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
        <h3 style="margin:0;">{{ $panelTitle }}</h3>
        @if($pdfUrl)
            <a href="{{ $pdfUrl }}">{{ $pdfLabel }}</a>
        @endif
    </div>

    <div style="border:1px solid #ddd; padding:12px; margin-bottom:10px;">
        {!! $svg !!}
    </div>

    @if(($showSummary && count($summary) > 0) || $showMemoCard)
        <div style="border:1px solid #e5e7eb; border-radius:8px; padding:10px; background:#f9fafb;">
            <div style="font-weight:700; margin-bottom:8px;">{{ $summaryTitle }}</div>
            @if($summaryUseTableLayout)
                @php
                    $summaryCells = [];
                    if ($showSummary) {
                        foreach ($summary as $item) {
                            $summaryCells[] = [
                                'type' => 'summary',
                                'item' => $item,
                            ];
                        }
                    }
                    if ($showMemoCard) {
                        $summaryCells[] = ['type' => 'memo'];
                    }
                    $summaryRowsTable = array_chunk($summaryCells, $summaryTableColumns);
                @endphp
                <table style="width:100%; table-layout:fixed; border-collapse:separate; border-spacing:{{ $summaryGapPx }}px;">
                    <tbody>
                        @foreach($summaryRowsTable as $rowItems)
                            <tr>
                                @foreach($rowItems as $cell)
                                    <td style="vertical-align:top; border:none; padding:0;">
                                        @if(($cell['type'] ?? '') === 'summary')
                                            @php
                                                $item = $cell['item'] ?? [];
                                                $label = (string)($item['label'] ?? '');
                                                $value = $item['value'] ?? null;
                                                $valueText = ($value === null || $value === '') ? '-' : (string)$value;
                                            @endphp
                                            <div style="{{ $summaryCardBaseStyle }}">
                                                <div class="muted">{{ $label }}</div>
                                                <div style="overflow-wrap:anywhere; word-break:break-word;">{{ $valueText }}</div>
                                            </div>
                                        @else
                                            <div style="{{ $summaryCardBaseStyle }}">
                                                <div class="muted">{{ $memoLabel }}</div>
                                                @if(!$memoReadonly && $memoUpdateUrl)
                                                    <form method="POST" action="{{ $memoUpdateUrl }}">
                                                        @csrf
                                                        @if(!in_array($memoHttpMethod, ['GET', 'POST'], true))
                                                            @method($memoHttpMethod)
                                                        @endif
                                                        <textarea name="{{ $memoFieldName }}" rows="{{ $memoRows }}" style="{{ $memoFieldStyle }} resize:none;">{{ old($memoFieldName, $memoValue) }}</textarea>
                                                        <div style="margin-top:6px;">
                                                            <button type="submit">{{ $memoButtonLabel }}</button>
                                                        </div>
                                                    </form>
                                                @else
                                                    <div style="{{ $memoFieldStyle }} white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word;">{{ $memoValue !== '' ? $memoValue : '（未入力）' }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                @for($i = count($rowItems); $i < $summaryTableColumns; $i++)
                                    <td style="border:none; padding:0;"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div style="{{ $summaryGridStyle }}">
                    @if($showSummary)
                        @foreach($summary as $item)
                            @php
                                $label = (string)($item['label'] ?? '');
                                $value = $item['value'] ?? null;
                                $valueText = ($value === null || $value === '') ? '-' : (string)$value;
                            @endphp
                            <div style="{{ $summaryCardStyle }}">
                                <div class="muted">{{ $label }}</div>
                                <div style="overflow-wrap:anywhere; word-break:break-word;">{{ $valueText }}</div>
                            </div>
                        @endforeach
                    @endif

                    @if($showMemoCard)
                        <div style="{{ $memoCardStyle }}">
                            <div class="muted">{{ $memoLabel }}</div>
                            @if(!$memoReadonly && $memoUpdateUrl)
                                <form method="POST" action="{{ $memoUpdateUrl }}">
                                    @csrf
                                    @if(!in_array($memoHttpMethod, ['GET', 'POST'], true))
                                        @method($memoHttpMethod)
                                    @endif
                                    <textarea name="{{ $memoFieldName }}" rows="{{ $memoRows }}" style="{{ $memoFieldStyle }} resize:none;">{{ old($memoFieldName, $memoValue) }}</textarea>
                                    <div style="margin-top:6px;">
                                        <button type="submit">{{ $memoButtonLabel }}</button>
                                    </div>
                                </form>
                            @else
                                <div style="{{ $memoFieldStyle }} white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word;">{{ $memoValue !== '' ? $memoValue : '（未入力）' }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if($showDetails)
        @if($detailsInToggle)
            <details style="margin-top:12px;">
                <summary>{{ $detailsSummaryLabel }}</summary>
        @else
            <div style="margin-top:12px;">
        @endif

            @if($showErrorTable)
                <h4>{{ $errorTableLabel }}</h4>
                <table>
                    <thead>
                        <tr>
                            <th>パス</th>
                            <th>メッセージ</th>
                            @if($showCreatorColumns)
                                <th>作成アカウント</th>
                                <th>メールアドレス</th>
                                <th>担当者</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @if(is_array($errors) && count($errors) > 0)
                            @foreach($errors as $e)
                                <tr>
                                    <td>{{ $e['path'] ?? '' }}</td>
                                    <td>{{ $e['message'] ?? '' }}</td>
                                    @if($showCreatorColumns)
                                        <td>{{ $creatorAccountDisplayText }}</td>
                                        <td>{{ $creatorEmailText }}</td>
                                        <td>{{ $creatorAssigneeText }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td>-</td>
                                <td>-</td>
                                @if($showCreatorColumns)
                                    <td>{{ $creatorAccountDisplayText }}</td>
                                    <td>{{ $creatorEmailText }}</td>
                                    <td>{{ $creatorAssigneeText }}</td>
                                @endif
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif

            @if($showConfigPriceTable)
                <h4>{{ $configTableLabel }}</h4>
                <table>
                    <thead>
                        <tr>
                            <th>種類</th>
                            <th>番号</th>
                            <th>パーツ名</th>
                            @if($showSourcePathColumn)
                                <th>source_path</th>
                            @endif
                            <th>長さ/範囲</th>
                            <th>許容誤差</th>
                            @if($showQuantityColumn)
                                <th>個数</th>
                            @endif
                            @if($showPriceColumns)
                                <th>単価(¥)</th>
                                <th>小計(¥)</th>
                            @endif
                            {{-- @if($showCreatorColumns)
                                <th>作成者アカウント表示名</th>
                                <th>登録メールアドレス</th>
                                <th>担当者</th>
                            @endif --}}
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $r)
                            <tr>
                                <td>{{ $r['type'] ?? '' }}</td>
                                <td>{{ $r['index'] ?? '' }}</td>
                                <td>
                                    @if($showSkuOnlyWhenPriced)
                                        {{ $r['priced_sku_name'] ?? '' }}
                                    @else
                                        {{ $r['sku_name'] ?? '' }}
                                    @endif
                                </td>
                                @if($showSourcePathColumn)
                                    <td>{{ $r['source_path'] ?? '' }}</td>
                                @endif
                                <td>{{ $r['range'] ?? '' }}</td>
                                <td>{{ $r['tolerance'] ?? '' }}</td>
                                @if($showQuantityColumn)
                                    <td>{{ $r['quantity'] ?? '' }}</td>
                                @endif
                                @if($showPriceColumns)
                                    <td>{{ $r['unit_price'] ?? '' }}</td>
                                    <td>{{ $r['line_total'] ?? '' }}</td>
                                @endif
                                {{-- @if($showCreatorColumns)
                                    <td>{{ $creatorAccountDisplayText }}</td>
                                    <td>{{ $creatorEmailText }}</td>
                                    <td>{{ $creatorAssigneeText }}</td>
                                @endif --}}
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($showJsonSection)
                <details style="margin-top:12px;">
                    <summary>JSONデータ</summary>
                    <h5>snapshot</h5>
                    <pre>{{ $snapshotJsonText }}</pre>
                    <h5>config</h5>
                    <pre>{{ $configJsonText }}</pre>
                    <h5>derived</h5>
                    <pre>{{ $derivedJsonText }}</pre>
                    <h5>validation_errors</h5>
                    <pre>{{ $errorsJsonText }}</pre>
                </details>
            @endif
        @if($detailsInToggle)
            </details>
        @else
            </div>
        @endif
    @endif
</div>
