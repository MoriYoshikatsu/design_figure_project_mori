<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>仕様書 #{{ $quote->id ?? '' }}</title>
    @php
        $fontSrc = !empty($fontPath ?? null) && str_starts_with((string)$fontPath, '/')
            ? 'file://' . $fontPath
            : ($fontPath ?? '');
        $fontBoldSrc = !empty($fontBoldPath ?? null) && str_starts_with((string)$fontBoldPath, '/')
            ? 'file://' . $fontBoldPath
            : ($fontBoldPath ?? '');
    @endphp
    <style>
        @page { margin: 18mm; }
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
            font-size: 13px;
        }
        /* td以外の文字も必ず同フォントで描画（SVG要素自体は除外） */
        h1, h2, h3, h4, h5, h6,
        p, div, span, a, strong, em,
        table, thead, tbody, tr, th, td,
        ul, ol, li, pre, code, small {
            font-family: 'JPFontUi', 'IPAGothic', 'IPAPGothic', DejaVu Sans, sans-serif !important;
        }
        @if(empty($fontBoldSrc))
        /* 太字フォントが無い環境では、太字要求時の□化を避ける */
        h1, h2, h3, h4, h5, h6, strong, th, b, .muted {
            font-weight: 400 !important;
        }
        @endif
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        pre { background:#f3f4f6; padding:8px; overflow:auto; }
        .muted { color:#6b7280; }
    </style>
</head>
<body>
    @php
        $snapshotView = is_array($snapshot ?? null) ? $snapshot : [];
        if (!isset($snapshotView['totals'])) {
            $snapshotView['totals'] = $totals ?? [];
        }
        $config = is_array($snapshotView['config'] ?? null) ? $snapshotView['config'] : [];
        $derived = is_array($snapshotView['derived'] ?? null) ? $snapshotView['derived'] : [];
        $errors = is_array($snapshotView['validation_errors'] ?? null) ? $snapshotView['validation_errors'] : [];
    @endphp

    <h1>仕様書 #{{ $quote->id ?? '' }}</h1>
    <table>
        <tbody>
            <tr><th>仕様書id</th><td>{{ $quote->id ?? '' }}</td></tr>
            <tr><th>作成日時</th><td>{{ $quote->created_at ?? '' }}</td></tr>
            <tr><th>ユーザー名</th><td>{{ $quote->account_name ?? '-' }}</td></tr>
            <tr><th>登録メールアドレス</th><td>{{ $quote->account_emails ?? '-' }}</td></tr>
            <tr><th>担当者名</th><td>{{ $quote->account_assignee_name ?? '-' }}</td></tr>
        </tbody>
    </table>

    @include('partials.snapshot_bundle', [
        'panelTitle' => 'スナップショット',
        'pdfUrl' => null,
        'includeAutoSummary' => false,
        'showDetails' => true,
        'detailsInToggle' => false,
        'detailsSummaryLabel' => '構成表',
        'configTableLabel' => '構成表',
        'showErrorTable' => false,
        'showSummary' => false,
        'showSourcePathColumn' => false,
        'showQuantityColumn' => false,
        'showPriceColumns' => false,
        'showSkuOnlyWhenPriced' => true,
        'showJsonSection' => false,
        'showMemoCard' => true,
        'memoValue' => $quote->display_memo ?? $quote->memo ?? '',
        'memoReadonly' => true,
        'memoLabel' => 'メモ（詳細な希望仕様などご記入ください）',
        'svg' => $snapshotGraphicHtml ?? '',
        'snapshot' => $snapshotView,
        'config' => $config,
        'derived' => $derived,
        'errors' => $errors,
    ])
</body>
</html>
