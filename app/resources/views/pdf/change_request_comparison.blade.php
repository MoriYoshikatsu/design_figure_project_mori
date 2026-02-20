<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>編集承認変更申請 #{{ $req->id ?? '' }} 比較</title>
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
        h2 { font-size: 14px; margin: 16px 0 8px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 5px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .section { page-break-inside: avoid; }
    </style>
</head>
<body>
    @php
        $baseSnapshotView = is_array($baseSnapshotView ?? null) ? $baseSnapshotView : [];
        $snapshotView = is_array($snapshotView ?? null) ? $snapshotView : [];

        $baseConfig = is_array($baseConfig ?? null) ? $baseConfig : (is_array($baseSnapshotView['config'] ?? null) ? $baseSnapshotView['config'] : []);
        $baseDerived = is_array($baseDerived ?? null) ? $baseDerived : (is_array($baseSnapshotView['derived'] ?? null) ? $baseSnapshotView['derived'] : []);
        $baseErrors = is_array($baseErrors ?? null) ? $baseErrors : (is_array($baseSnapshotView['validation_errors'] ?? null) ? $baseSnapshotView['validation_errors'] : []);

        $config = is_array($config ?? null) ? $config : (is_array($snapshotView['config'] ?? null) ? $snapshotView['config'] : []);
        $derived = is_array($derived ?? null) ? $derived : (is_array($snapshotView['derived'] ?? null) ? $snapshotView['derived'] : []);
        $errors = is_array($errors ?? null) ? $errors : (is_array($snapshotView['validation_errors'] ?? null) ? $snapshotView['validation_errors'] : []);

        $baseBom = is_array($baseSnapshotView['bom'] ?? null) ? $baseSnapshotView['bom'] : [];
        $newBom = is_array($snapshotView['bom'] ?? null) ? $snapshotView['bom'] : [];
        $basePricing = is_array($baseSnapshotView['pricing'] ?? null) ? $baseSnapshotView['pricing'] : [];
        $newPricing = is_array($snapshotView['pricing'] ?? null) ? $snapshotView['pricing'] : [];
        $baseTotals = is_array($baseSnapshotView['totals'] ?? null) ? $baseSnapshotView['totals'] : [];
        $newTotals = is_array($snapshotView['totals'] ?? null) ? $snapshotView['totals'] : [];

        $baseSummaryItems = [
            ['label' => '対象', 'value' => ($req->entity_type ?? '').' #'.($req->entity_id ?? '')],
            ['label' => 'ステータス', 'value' => $req->status ?? ''],
            ['label' => 'MFD数', 'value' => $baseConfig['mfdCount'] ?? '-'],
            ['label' => 'チューブ数', 'value' => $baseConfig['tubeCount'] ?? '-'],
            ['label' => 'エラー件数', 'value' => count($baseErrors)],
        ];
        $newSummaryItems = [
            ['label' => '対象', 'value' => ($req->entity_type ?? '').' #'.($req->entity_id ?? '')],
            ['label' => 'ステータス', 'value' => $req->status ?? ''],
            ['label' => 'MFD数', 'value' => $config['mfdCount'] ?? '-'],
            ['label' => 'チューブ数', 'value' => $config['tubeCount'] ?? '-'],
            ['label' => 'エラー件数', 'value' => count($errors)],
        ];
        if (($req->entity_type ?? '') === 'quote') {
            $baseSummaryItems[] = ['label' => '合計', 'value' => $baseTotals['total'] ?? '-'];
            $newSummaryItems[] = ['label' => '合計', 'value' => $newTotals['total'] ?? '-'];
        }
    @endphp

    <h1>編集承認変更申請 #{{ $req->id ?? '' }} 比較</h1>

    <div class="section">
        <h2>承認結果</h2>
        <table>
            <tbody>
                <tr>
                    <th style="width:16%;">ステータス</th>
                    <td style="width:17%;">{{ $req->status ?? '' }}</td>
                    <th style="width:16%;">承認者</th>
                    <td style="width:17%;">{{ $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-') }}</td>
                    <th style="width:16%;">承認日時</th>
                    <td style="width:18%;">{{ $req->approved_at ?? '-' }}</td>
                </tr>
                <tr>
                    <th>申請者</th>
                    <td>{{ $req->requested_by_account_display_name ?? '-' }}</td>
                    <th>担当者</th>
                    <td>{{ $req->requested_by_assignee_name ?? '-' }}</td>
                    <th>コメント</th>
                    <td>{{ $req->comment ?? '（なし）' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>概要比較</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:34%;">項目</th>
                    <th style="width:33%;">編集前（初版）</th>
                    <th style="width:33%;">編集後（申請内容）</th>
                </tr>
            </thead>
            <tbody>
                <tr><th>MFD数</th><td>{{ $baseConfig['mfdCount'] ?? '-' }}</td><td>{{ $config['mfdCount'] ?? '-' }}</td></tr>
                <tr><th>チューブ数</th><td>{{ $baseConfig['tubeCount'] ?? '-' }}</td><td>{{ $config['tubeCount'] ?? '-' }}</td></tr>
                <tr><th>エラー件数</th><td>{{ count($baseErrors) }}</td><td>{{ count($errors) }}</td></tr>
                <tr><th>BOM件数</th><td>{{ count($baseBom) }}</td><td>{{ count($newBom) }}</td></tr>
                <tr><th>価格内訳件数</th><td>{{ count($basePricing) }}</td><td>{{ count($newPricing) }}</td></tr>
                <tr><th>申請者</th><td>{{ $req->requested_by_account_display_name ?? '-' }}</td><td>{{ $req->requested_by_account_display_name ?? '-' }}</td></tr>
                @if(($req->entity_type ?? '') === 'quote')
                    <tr><th>合計</th><td>{{ $baseTotals['total'] ?? '-' }}</td><td>{{ $newTotals['total'] ?? '-' }}</td></tr>
                @endif
                <tr><th>担当者</th><td>{{ $req->requested_by_assignee_name ?? '-' }}</td><td>{{ $req->requested_by_assignee_name ?? '-' }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        @include('snapshot_bundle', [
            'panelTitle' => '初版SVGrenderer',
            'pdfUrl' => null,
            'includeAutoSummary' => true,
            'showDetails' => true,
            'detailsInToggle' => false,
                'showJsonSection' => false,
                'summaryUseTableLayout' => true,
                'summaryTableColumns' => 4,
                'summaryTitle' => '初版概要',
                'errorTableLabel' => '初版検証エラー',
                'showCreatorColumns' => false,
                'summaryItems' => $baseSummaryItems,
                'svg' => $baseGraphicHtml ?? '',
            'snapshot' => $baseSnapshotView,
            'config' => $baseConfig,
            'derived' => $baseDerived,
            'errors' => $baseErrors,
            'configTableLabel' => '初版構成価格表',
        ])
    </div>

    <div class="section">
        @include('snapshot_bundle', [
            'panelTitle' => '申請内容SVGrenderer',
            'pdfUrl' => null,
            'includeAutoSummary' => true,
            'showDetails' => true,
            'detailsInToggle' => false,
            'showJsonSection' => false,
            'summaryUseTableLayout' => true,
            'summaryTableColumns' => 4,
            'summaryTitle' => '申請内容概要',
            'errorTableLabel' => '申請内容検証エラー',
            'showCreatorColumns' => false,
            'summaryItems' => $newSummaryItems,
            'svg' => $newGraphicHtml ?? '',
            'snapshot' => $snapshotView,
            'config' => $config,
            'derived' => $derived,
            'errors' => $errors,
            'configTableLabel' => '申請内容構成価格表',
        ])
    </div>
</body>
</html>
