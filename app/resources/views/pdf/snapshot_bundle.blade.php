<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'スナップショット' }}</title>
    @php
        $fontSrc = !empty($fontPath ?? null) && str_starts_with((string)$fontPath, '/')
            ? 'file://' . $fontPath
            : ($fontPath ?? '');
        $fontBoldSrc = !empty($fontBoldPath ?? null) && str_starts_with((string)$fontBoldPath, '/')
            ? 'file://' . $fontBoldPath
            : ($fontBoldPath ?? '');
    @endphp
    <style>
        @page { margin: 14mm; }
        @font-face {
            font-family: 'JPFontUi';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $fontSrc }}') format('truetype');
        }
        @if(!empty($fontBoldSrc))
        @font-face {
            font-family: 'JPFontUi';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $fontBoldSrc }}') format('truetype');
        }
        @endif
        body {
            font-family: 'JPFontUi', 'IPAGothic', 'IPAPGothic', DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        h1 { font-size: 18px; margin: 0 0 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 5px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        pre { background:#f3f4f6; padding:8px; overflow:auto; }
    </style>
</head>
<body>
    @php
        $snapshotView = is_array($snapshot ?? null) ? $snapshot : [];
        $configView = is_array($config ?? null) ? $config : (is_array($snapshotView['config'] ?? null) ? $snapshotView['config'] : []);
        $derivedView = is_array($derived ?? null) ? $derived : (is_array($snapshotView['derived'] ?? null) ? $snapshotView['derived'] : []);
        $errorsView = is_array($errors ?? null) ? $errors : (is_array($snapshotView['validation_errors'] ?? null) ? $snapshotView['validation_errors'] : []);
    @endphp

    <h1>{{ $title ?? 'スナップショット' }}</h1>

    @include('snapshot_bundle', [
        'panelTitle' => $panelTitle ?? 'スナップショット',
        'pdfUrl' => null,
        'summaryItems' => is_array($summaryItems ?? null) ? $summaryItems : [],
        'includeAutoSummary' => (bool)($includeAutoSummary ?? true),
        'showDetails' => true,
        'detailsInToggle' => false,
        'detailsSummaryLabel' => '詳細',
        'showErrorTable' => (bool)($showErrorTable ?? true),
        'showConfigPriceTable' => (bool)($showConfigPriceTable ?? true),
        'showSummary' => (bool)($showSummary ?? true),
        'showSourcePathColumn' => (bool)($showSourcePathColumn ?? true),
        'showQuantityColumn' => (bool)($showQuantityColumn ?? true),
        'showPriceColumns' => (bool)($showPriceColumns ?? true),
        'showSkuOnlyWhenPriced' => (bool)($showSkuOnlyWhenPriced ?? false),
        'configTableLabel' => (string)($configTableLabel ?? '構成価格表'),
        'showJsonSection' => false,
        'showMemoCard' => (bool)($showMemoCard ?? false),
        'memoValue' => (string)($memoValue ?? ''),
        'memoReadonly' => true,
        'memoLabel' => (string)($memoLabel ?? 'メモ'),
        'showCreatorColumns' => (bool)($showCreatorColumns ?? false),
        'creatorAccountDisplayName' => (string)($creatorAccountDisplayName ?? ''),
        'creatorEmail' => (string)($creatorEmail ?? ''),
        'creatorAssigneeName' => (string)($creatorAssigneeName ?? ''),
        'summaryUseTableLayout' => true,
        'summaryTableColumns' => max(1, (int)($summaryTableColumns ?? 4)),
        'summaryGapPx' => max(0, (int)($summaryGapPx ?? 8)),
        'svg' => $snapshotGraphicHtml ?? '',
        'snapshot' => $snapshotView,
        'config' => $configView,
        'derived' => $derivedView,
        'errors' => $errorsView,
    ])
</body>
</html>
