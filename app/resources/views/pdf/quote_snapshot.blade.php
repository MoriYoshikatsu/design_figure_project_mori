<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Specification Sheet</title>
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
        /* Force the same font for text elements other than the SVG itself. */
        h1, h2, h3, h4, h5, h6,
        p, div, span, a, strong, em,
        table, thead, tbody, tr, th, td,
        ul, ol, li, pre, code, small {
            font-family: 'JPFontUi', 'IPAGothic', 'IPAPGothic', DejaVu Sans, sans-serif !important;
        }
        @if(empty($fontBoldSrc))
        /* Avoid missing-glyph boxes when a bold font file is unavailable. */
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

    <h1>Specification Sheet</h1>
    <table>
        <tbody>
            <tr><th>Created At</th><td>{{ $quote->created_at ?? '' }}</td></tr>
            <tr><th>User Name</th><td>{{ $quote->account_name ?? '-' }}</td></tr>
            <tr><th>Registered Email Address</th><td>{{ $quote->account_emails ?? '-' }}</td></tr>
            <tr><th>Assignee Name</th><td>{{ $quote->account_assignee_name ?? '-' }}</td></tr>
        </tbody>
    </table>

    @include('snapshot_bundle', [
        'uiLanguage' => 'en',
        'panelTitle' => 'Snapshot',
        'pdfUrl' => null,
        'includeAutoSummary' => false,
        'showDetails' => true,
        'detailsInToggle' => false,
        'detailsSummaryLabel' => 'Configuration Table',
        'configTableLabel' => 'Configuration Table',
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
        'memoLabel' => 'Notes (Please enter detailed requirements or specifications.)',
        'svg' => $snapshotGraphicHtml ?? '',
        'snapshot' => $snapshotView,
        'config' => $config,
        'derived' => $derived,
        'errors' => $errors,
    ])
</body>
</html>
