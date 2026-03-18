<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Specification Sheet #{{ $quote->id ?? '' }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding:16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 6px; }
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
        $config = is_array($snapshotView['config'] ?? null) ? $snapshotView['config'] : [];
        $fibers = is_array($config['fibers'] ?? null) ? $config['fibers'] : [];
        $sleeves = is_array($config['sleeves'] ?? null) ? $config['sleeves'] : [];
        $tubes = is_array($config['tubes'] ?? null) ? $config['tubes'] : [];
        $connectors = is_array($config['connectors'] ?? null) ? $config['connectors'] : [];
    @endphp

    <h1>Specification Sheet #{{ $quote->id ?? '' }}</h1>
    <table>
        <tbody>
            <tr><th>Specification Sheet ID</th><td>{{ $quote->id ?? '' }}</td></tr>
            <tr><th>Created At</th><td>{{ $quote->created_at ?? '' }}</td></tr>
            <tr><th>Account</th><td>{{ $quote->account_name ?? '-' }}</td></tr>
            <tr><th>Email Address</th><td>{{ $quote->account_emails ?? '-' }}</td></tr>
            <tr><th>Assignee</th><td>{{ $quote->account_assignee_name ?? '-' }}</td></tr>
        </tbody>
    </table>

    @include('snapshot_bundle', [
        'uiLanguage' => 'en',
        'panelTitle' => 'Snapshot',
        'pdfUrl' => route('quotes.snapshot.pdf', $quote->id),
        // 'summaryItems' => [
        //     ['label' => '見積ID', 'value' => $quote->id ?? ''],
        //     ['label' => 'ステータス', 'value' => $quote->status ?? ''],
        //     ['label' => '通貨', 'value' => $quote->currency ?? ''],
        //     ['label' => '作成日時', 'value' => $quote->created_at ?? ''],
        // ],
        'includeAutoSummary' => false,
        'showDetails' => true,
        'detailsInToggle' => false,
        'pdfLabel' => 'Download PDF',
        'detailsSummaryLabel' => 'Configuration & Pricing',
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
        'memoLabel' => 'Notes (Detailed requirements, shipping address, etc.)',
        'svg' => $svg,
        'snapshot' => $snapshotView,
        'config' => $config,
        'derived' => $derived,
        'errors' => $errors,
    ])
    @include('partials.back_to_top', ['backToTopLabel' => 'Back to top'])
</body>
</html>
