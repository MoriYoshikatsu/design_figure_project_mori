@php
    $uiLanguage = strtolower((string)($uiLanguage ?? 'ja'));
    $isEnglish = $uiLanguage === 'en';
    $t = static fn(string $ja, string $en): string => $isEnglish ? $en : $ja;
    $translateTecSide = static function (?string $side) use ($t): string {
        return match (strtolower(trim((string)$side))) {
            'left' => $t('左端', 'Left End'),
            'right' => $t('右端', 'Right End'),
            default => $t('未指定', 'Not Set'),
        };
    };

    $panelTitle = $panelTitle ?? $t('スナップショット', 'Snapshot');
    $pdfUrl = $pdfUrl ?? null;
    $pdfLabel = $pdfLabel ?? $t('PDFダウンロード', 'Download PDF');
    $summaryItems = is_array($summaryItems ?? null) ? $summaryItems : [];
    $includeAutoSummary = (bool)($includeAutoSummary ?? true);
    $showDetails = (bool)($showDetails ?? true);
    $detailsInToggle = (bool)($detailsInToggle ?? true);
    $detailsSummaryLabel = (string)($detailsSummaryLabel ?? $t('詳細（エラー・構成価格表・JSON）', 'Details (Errors, Configuration & Pricing, JSON)'));
    $showErrorTable = (bool)($showErrorTable ?? true);
    $showConfigPriceTable = (bool)($showConfigPriceTable ?? true);
    $showSummary = (bool)($showSummary ?? true);
    $summaryTitle = (string)($summaryTitle ?? $t('概要', 'Summary'));
    $errorTableLabel = (string)($errorTableLabel ?? $t('検証エラー', 'Validation Errors'));
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
    $configTableLabel = (string)($configTableLabel ?? $t('構成価格表', 'Configuration & Pricing Table'));
    $showJsonSection = (bool)($showJsonSection ?? true);
    $showMemoCard = (bool)($showMemoCard ?? false);
    $memoValue = (string)($memoValue ?? '');
    $memoLabel = (string)($memoLabel ?? $t('メモ', 'Notes'));
    $memoUpdateUrl = $memoUpdateUrl ?? null;
    $memoFieldName = (string)($memoFieldName ?? 'memo');
    $memoRows = max(2, (int)($memoRows ?? 3));
    $memoFixedHeightPx = max(32, (int)($memoFixedHeightPx ?? 40));
    $memoButtonLabel = (string)($memoButtonLabel ?? $t('メモ保存', 'Save Notes'));
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
    $localizedSkuNameByCode = [];
    if ($isEnglish || empty($skuNameByCode)) {
        $localizedSkuNameByCode = app(\App\Services\SkuDisplayNameService::class)->buildNameMap($uiLanguage);
    }
    if (!empty($localizedSkuNameByCode)) {
        $skuNameByCode = $localizedSkuNameByCode;
    }
    $toSkuName = static function (?string $code) use ($skuNameByCode): string {
        if ($code === null || $code === '') {
            return '';
        }
        return (string)($skuNameByCode[$code] ?? '');
    };
    $bomPartCode = static function (array $row): string {
        return (string)($row['part_code'] ?? ($row['sku_code'] ?? ''));
    };
    $summaryMoneyLabels = [
        '小計' => true,
        '税' => true,
        '合計' => true,
        'Subtotal' => true,
        'Tax' => true,
        'Total' => true,
        'subtotal' => true,
        'tax' => true,
        'total' => true,
    ];
    $emptyMemoText = $t('（未入力）', '(Not entered)');
    $jsonSummaryLabel = $t('JSONデータ', 'JSON Data');
    $toSummaryValueText = static function (array $item) use ($summaryMoneyLabels): string {
        $label = trim((string)($item['label'] ?? ''));
        $value = $item['value'] ?? null;
        if ($value === null || $value === '') {
            return '-';
        }
        if (isset($summaryMoneyLabels[$label])) {
            return format_amount($value);
        }
        return (string)$value;
    };
    $toMoneyText = static function (mixed $value, string $empty = ''): string {
        return format_amount($value, $empty);
    };

    $sleeves = is_array($config['sleeves'] ?? null) ? $config['sleeves'] : [];
    $fibers = is_array($config['fibers'] ?? null) ? $config['fibers'] : [];
    $tubes = is_array($config['tubes'] ?? null) ? $config['tubes'] : [];
    $connectors = is_array($config['connectors'] ?? null) ? $config['connectors'] : [];
    $processType = strtoupper((string)($config['processType'] ?? 'MFD'));
    if (!in_array($processType, ['MFD', 'TEC20', 'TEC30', 'TEC20_HP', 'TEC30_HP'], true)) {
        $processType = 'MFD';
    }
    $isTecMode = in_array($processType, ['TEC20', 'TEC30', 'TEC20_HP', 'TEC30_HP'], true);

    $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
    $bom = is_array($snapshot['bom'] ?? null) ? $snapshot['bom'] : [];
    $pricing = is_array($snapshot['pricing'] ?? null) ? $snapshot['pricing'] : [];
    $pricingInput = is_array($snapshot['pricing_input'] ?? null) ? $snapshot['pricing_input'] : [];
    $orderQtySummary = $pricingInput['order_qty'] ?? ($snapshot['order_qty'] ?? null);

    $pricingBySort = [];
    foreach ($pricing as $p) {
        if (!is_array($p)) continue;
        $pricingBySort[(int)($p['sort_order'] ?? 0)] = $p;
    }

    $bomByPath = [];
    $bomFirstBySku = [];
    $processSkuCodeByType = [
        'MFD' => 'PROC_MFD_CONVERSION',
        'TEC20' => 'PROC_TEC20',
        'TEC30' => 'PROC_TEC30',
        'TEC20_HP' => 'PROC_TEC20_HP',
        'TEC30_HP' => 'PROC_TEC30_HP',
    ];
    $selectedProcessSkuCode = (string)($processSkuCodeByType[$processType] ?? 'PROC_MFD_CONVERSION');
    $processBom = null;
    foreach ($bom as $b) {
        if (!is_array($b)) continue;
        $sortKey = (int)($b['sort_order'] ?? 0);
        $path = (string)($b['source_path'] ?? '');
        $skuCode = $bomPartCode($b);
        $priceRow = $pricingBySort[$sortKey] ?? [];
        $row = [
            'part_code' => $skuCode,
            'quantity' => $b['quantity'] ?? '',
            'source_path' => $path,
            'unit_price' => $priceRow['unit_price'] ?? '',
            'line_total' => $priceRow['line_total'] ?? '',
        ];
        if ($processBom === null && ($path === '$.processType' || $skuCode === $selectedProcessSkuCode)) {
            $processBom = $row;
        } elseif ($processBom === null && $processType === 'MFD' && $skuCode === 'PROC_MFD_CONVERSION') {
            $processBom = $row;
        }
        if ($path !== '') {
            $bomByPath[$path] = $row;
        }
        if ($skuCode !== '' && !array_key_exists($skuCode, $bomFirstBySku)) {
            $bomFirstBySku[$skuCode] = $row;
        }
    }

    $mfdCount = $isTecMode ? 0 : 1;
    $mfdQty = is_numeric($processBom['quantity'] ?? null) ? (float)$processBom['quantity'] : 0.0;
    $mfdLineTotal = is_numeric($processBom['line_total'] ?? null) ? (float)$processBom['line_total'] : 0.0;
    $mfdLineEach = $mfdQty > 0 ? ($mfdLineTotal / $mfdQty) : 0.0;

    $rows = [];
    if ($isTecMode) {
        $processSelectedSku = $selectedProcessSkuCode;
        $processPricedSku = is_array($processBom) ? $bomPartCode($processBom) : '';
        $rows[] = [
            'type' => $t('TEC工程', 'TEC Process'),
            'index' => '',
            'part_code' => $processSelectedSku,
            'priced_part_code' => ($processSelectedSku !== '' && $processPricedSku === $processSelectedSku) ? $processPricedSku : '',
            'sku_name' => $toSkuName($processSelectedSku),
            'priced_sku_name' => ($processSelectedSku !== '' && $processPricedSku === $processSelectedSku) ? $toSkuName($processPricedSku) : '',
            'source_path' => $processBom['source_path'] ?? '$.processType',
            'range' => $processType,
            'tolerance' => '-',
            'quantity' => $processBom['quantity'] ?? '1',
            'unit_price' => $processBom['unit_price'] ?? '',
            'line_total' => $processBom['line_total'] ?? '',
        ];
    } else {
        for ($i = 0; $i < $mfdCount; $i++) {
            $mfdSkuCode = is_array($processBom) ? $bomPartCode($processBom) : '';
            $rows[] = [
                'type' => $t('MFD変換', 'MFD Conversion'),
                'index' => '['.$i.']',
                'part_code' => $mfdSkuCode,
                'priced_part_code' => $mfdSkuCode,
                'sku_name' => $toSkuName($mfdSkuCode),
                'priced_sku_name' => $toSkuName($mfdSkuCode),
                'source_path' => $processBom['source_path'] ?? '$.processType',
                'range' => '-',
                'tolerance' => '-',
                'quantity' => '1',
                'unit_price' => $processBom['unit_price'] ?? '',
                'line_total' => $mfdQty > 0 ? number_format($mfdLineEach, 2, '.', '') : '',
            ];
        }
    }

    if (!$isTecMode) {
        foreach ($sleeves as $i => $s) {
            $path = '$.sleeves['.$i.']';
            $r = $bomByPath[$path] ?? null;
            $selectedSku = (string)($s['skuCode'] ?? '');
            $pricedSku = is_array($r) ? $bomPartCode($r) : '';
            $rows[] = [
                'type' => $t('スリーブ(MFD)', 'Sleeve (MFD)'),
                'index' => '['.$i.']',
                'part_code' => $selectedSku,
                'priced_part_code' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $pricedSku : '',
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
    }

    foreach ($fibers as $i => $f) {
        $path = '$.fibers['.$i.']';
        $r = $bomByPath[$path] ?? null;
        $selectedSku = (string)($f['skuCode'] ?? '');
        $pricedSku = is_array($r) ? $bomPartCode($r) : '';
        $fiberLength = $f['lengthM'] ?? null;
        if (!is_numeric($fiberLength) && is_numeric($f['lengthMm'] ?? null)) {
            $fiberLength = (float)$f['lengthMm'] / 1000;
        }
        $fiberTolerance = $f['toleranceM'] ?? null;
        if (!is_numeric($fiberTolerance) && is_numeric($f['toleranceMm'] ?? null)) {
            $fiberTolerance = (float)$f['toleranceMm'] / 1000;
        }
        $rows[] = [
            'type' => $t('ファイバ(F)', 'Fiber (F)'),
            'index' => '['.$i.']',
            'part_code' => $selectedSku,
            'priced_part_code' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $pricedSku : '',
            'sku_name' => $toSkuName($selectedSku),
            'priced_sku_name' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $toSkuName($pricedSku) : '',
            'source_path' => $r['source_path'] ?? $path,
            'range' => is_numeric($fiberLength) ? ($fiberLength.'m') : '',
            'tolerance' => is_numeric($fiberTolerance) ? ('±'.$fiberTolerance.'m') : '',
            'quantity' => $r['quantity'] ?? '',
            'unit_price' => $r['unit_price'] ?? '',
            'line_total' => $r['line_total'] ?? '',
        ];
    }

    foreach ($tubes as $i => $tube) {
        $path = '$.tubes['.$i.']';
        $r = $bomByPath[$path] ?? null;
        $selectedSku = (string)($tube['skuCode'] ?? '');
        $pricedSku = is_array($r) ? $bomPartCode($r) : '';
        $sf = $tube['startFiberIndex'] ?? $tube['targetFiberIndex'] ?? '';
        $ef = $tube['endFiberIndex'] ?? $tube['targetFiberIndex'] ?? '';
        $so = $tube['startOffsetM'] ?? null;
        if (!is_numeric($so) && is_numeric($tube['startOffsetMm'] ?? null)) {
            $so = (float)$tube['startOffsetMm'] / 1000;
        }
        $eo = $tube['endOffsetM'] ?? null;
        if (!is_numeric($eo) && is_numeric($tube['endOffsetMm'] ?? null)) {
            $eo = (float)$tube['endOffsetMm'] / 1000;
        }
        $soText = is_numeric($so) ? ($so . 'm') : '-';
        $eoText = is_numeric($eo) ? ($eo . 'm') : '-';
        $range = ($sf !== '' || $ef !== '') ? ('F'.$sf.'+'.$soText.' → F'.$ef.'+'.$eoText) : '';
        $tubeTolerance = $tube['toleranceM'] ?? null;
        if (!is_numeric($tubeTolerance) && is_numeric($tube['toleranceMm'] ?? null)) {
            $tubeTolerance = (float)$tube['toleranceMm'] / 1000;
        }
        $rows[] = [
            'type' => $t('チューブ(T)', 'Tube (T)'),
            'index' => '['.$i.']',
            'part_code' => $selectedSku,
            'priced_part_code' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $pricedSku : '',
            'sku_name' => $toSkuName($selectedSku),
            'priced_sku_name' => ($selectedSku !== '' && $pricedSku === $selectedSku) ? $toSkuName($pricedSku) : '',
            'source_path' => $r['source_path'] ?? $path,
            'range' => $range,
            'tolerance' => is_numeric($tubeTolerance) ? ('±'.$tubeTolerance.'m') : '',
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
    $leftPricedSku = is_array($leftRow) ? $bomPartCode($leftRow) : '';
    if ($showLeftConnector) {
        $rows[] = [
            'type' => $t('コネクタ', 'Connector'),
            'index' => $t('左端', 'Left End'),
            'part_code' => $leftSku,
            'priced_part_code' => ($leftSku !== '' && $leftPricedSku === $leftSku) ? $leftPricedSku : '',
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
    $rightPricedSku = is_array($rightRow) ? $bomPartCode($rightRow) : '';
    if ($showRightConnector) {
        $rows[] = [
            'type' => $t('コネクタ', 'Connector'),
            'index' => $t('右端', 'Right End'),
            'part_code' => $rightSku,
            'priced_part_code' => ($rightSku !== '' && $rightPricedSku === $rightSku) ? $rightPricedSku : '',
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
            'part_code' => '',
            'priced_part_code' => '',
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
        ['label' => $t('ルールテンプレ', 'Rule Template'), 'value' => $snapshot['template_version_id'] ?? ''],
        ['label' => $t('納品物価格表', 'Price Book'), 'value' => $snapshot['price_book_id'] ?? ''],
        ['label' => $t('注文数量', 'Order Quantity'), 'value' => $orderQtySummary],
        ['label' => $t('工程種別', 'Process Type'), 'value' => $processType],
        ['label' => $t('TEC位置', 'TEC Position'), 'value' => $isTecMode ? $translateTecSide($config['tecSide'] ?? '') : '-'],
        ['label' => $t('MFD数', 'MFD Count'), 'value' => $isTecMode ? '-' : '1'],
        ['label' => $t('チューブ数', 'Tube Count'), 'value' => $config['tubeCount'] ?? ''],
        ['label' => $t('エラー件数', 'Error Count'), 'value' => is_array($errors) ? count($errors) : 0],
        ['label' => $t('BOM件数', 'BOM Count'), 'value' => count($bom)],
        ['label' => $t('価格内訳件数', 'Pricing Line Count'), 'value' => count($pricing)],
        ['label' => $t('小計', 'Subtotal'), 'value' => $totals['subtotal'] ?? ''],
        ['label' => $t('税', 'Tax'), 'value' => $totals['tax'] ?? ''],
        ['label' => $t('合計', 'Total'), 'value' => $totals['total'] ?? ''],
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
                                                $valueText = $toSummaryValueText($item);
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
                                                    <div style="{{ $memoFieldStyle }} white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word;">{{ $memoValue !== '' ? $memoValue : $emptyMemoText }}</div>
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
                                $valueText = $toSummaryValueText($item);
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
                                <div style="{{ $memoFieldStyle }} white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word;">{{ $memoValue !== '' ? $memoValue : $emptyMemoText }}</div>
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
                            <th>{{ $t('パス', 'Path') }}</th>
                            <th>{{ $t('メッセージ', 'Message') }}</th>
                            @if($showCreatorColumns)
                                <th>{{ $t('作成アカウント', 'Created Account') }}</th>
                                <th>{{ $t('メールアドレス', 'Email Address') }}</th>
                                <th>{{ $t('担当者', 'Assignee') }}</th>
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
                            <th>{{ $t('種類', 'Type') }}</th>
                            <th>{{ $t('番号', 'No.') }}</th>
                            <th>{{ $t('パーツ名', 'Part Name') }}</th>
                            @if($showSourcePathColumn)
                                <th>source_path</th>
                            @endif
                            <th>{{ $t('長さ/範囲', 'Length / Range') }}</th>
                            <th>{{ $t('許容誤差', 'Tolerance') }}</th>
                            @if($showQuantityColumn)
                                <th>{{ $t('個数', 'Qty') }}</th>
                            @endif
                            @if($showPriceColumns)
                                <th>{{ $t('単価(¥)', 'Unit Price (JPY)') }}</th>
                                <th>{{ $t('小計(¥)', 'Line Total (JPY)') }}</th>
                            @endif
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
                                    <td>{{ $toMoneyText($r['unit_price'] ?? null, '') }}</td>
                                    <td>{{ $toMoneyText($r['line_total'] ?? null, '') }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($showJsonSection)
                <details style="margin-top:12px;">
                    <summary>{{ $jsonSummaryLabel }}</summary>
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
