@extends('admin.layout')

@section('content')
    <h1>リクエスト #{{ $req->id ?? '' }} 詳細</h1>

    @php
        $canApprove = $canApprove ?? true;
        $snapshotPdfUrl = $snapshotPdfUrl ?? route('admin.change-requests.snapshot.pdf', $req->id);
        $baseSnapshotPdfUrl = $baseSnapshotPdfUrl ?? route('admin.change-requests.snapshot-base.pdf', $req->id);
        $compareSnapshotPdfUrl = $compareSnapshotPdfUrl ?? route('admin.change-requests.snapshot-compare.pdf', $req->id);

        $config = json_decode($configJson ?? '[]', true) ?? [];
        $derived = json_decode($derivedJson ?? '[]', true) ?? [];
        $errors = json_decode($errorsJson ?? '[]', true) ?? [];
        $baseConfig = json_decode($baseConfigJson ?? '[]', true) ?? [];
        $baseDerived = json_decode($baseDerivedJson ?? '[]', true) ?? [];
        $baseErrors = json_decode($baseErrorsJson ?? '[]', true) ?? [];

        $snapshotView = is_array($snapshot ?? null) ? $snapshot : [];
        if (!isset($snapshotView['config'])) $snapshotView['config'] = $config;
        if (!isset($snapshotView['derived'])) $snapshotView['derived'] = $derived;
        if (!isset($snapshotView['validation_errors'])) $snapshotView['validation_errors'] = $errors;
        if (!isset($snapshotView['bom']) || !is_array($snapshotView['bom'])) $snapshotView['bom'] = [];
        if (!isset($snapshotView['pricing']) || !is_array($snapshotView['pricing'])) $snapshotView['pricing'] = [];
        if (!isset($snapshotView['totals']) || !is_array($snapshotView['totals'])) $snapshotView['totals'] = [];
        $requestMemo = trim((string)($snapshotView['memo'] ?? ''));

        $baseSnapshotView = is_array($baseSnapshot ?? null) ? $baseSnapshot : [];
        if (!isset($baseSnapshotView['config'])) $baseSnapshotView['config'] = $baseConfig;
        if (!isset($baseSnapshotView['derived'])) $baseSnapshotView['derived'] = $baseDerived;
        if (!isset($baseSnapshotView['validation_errors'])) $baseSnapshotView['validation_errors'] = $baseErrors;
        if (!isset($baseSnapshotView['bom']) || !is_array($baseSnapshotView['bom'])) $baseSnapshotView['bom'] = [];
        if (!isset($baseSnapshotView['pricing']) || !is_array($baseSnapshotView['pricing'])) $baseSnapshotView['pricing'] = [];
        if (!isset($baseSnapshotView['totals']) || !is_array($baseSnapshotView['totals'])) $baseSnapshotView['totals'] = [];
        $baseMemo = trim((string)($baseSnapshotView['memo'] ?? ''));
        $requestedByRole = trim((string)($req->requested_by_role ?? ''));
        $requestedByLabel = trim((string)($req->requested_by_account_display_name ?? ''));
        if ($requestedByLabel === '') {
            $requestedByLabel = $req->requested_by ? ('ID: '.$req->requested_by) : '-';
        }
        if ($requestedByRole !== '') {
            $requestedByLabel .= ' ('.strtoupper($requestedByRole).')';
        }
    @endphp

    @if($canApprove && $req->status === 'PENDING')
        <div class="actions" style="margin-top:12px;">
            <form method="POST" action="{{ route('admin.change-requests.approve', $req->id) }}">
                @csrf
                <button type="submit">承認</button>
            </form>
            <form method="POST" action="{{ route('admin.change-requests.reject', $req->id) }}">
                @csrf
                <button type="submit">却下</button>
            </form>
        </div>
    @endif

    <div style="margin-top:12px;">
        <a href="{{ $compareSnapshotPdfUrl }}">比較PDFダウンロード（編集前/編集後）</a>
    </div>

    <h3>承認結果</h3>
    <table>
        <thead>
            <tr>
                <th>ステータス</th>
                <th>承認者</th>
                <th>承認日時</th>
                <th>作成者アカウント表示名</th>
                <th>担当者</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $req->status }}</td>
                <td>{{ $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-') }}</td>
                <td>{{ $req->approved_at }}</td>
                <td>{{ $requestedByLabel }}</td>
                <td>{{ $req->requested_by_assignee_name ?? '-' }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($baseSvg))
        @php
            $baseConfigCmp = is_array($baseSnapshotView['config'] ?? null) ? $baseSnapshotView['config'] : [];
            $newConfigCmp = is_array($snapshotView['config'] ?? null) ? $snapshotView['config'] : [];
            $baseErrorsCmp = is_array($baseSnapshotView['validation_errors'] ?? null) ? $baseSnapshotView['validation_errors'] : [];
            $newErrorsCmp = is_array($snapshotView['validation_errors'] ?? null) ? $snapshotView['validation_errors'] : [];
            $baseBomCmp = is_array($baseSnapshotView['bom'] ?? null) ? $baseSnapshotView['bom'] : [];
            $newBomCmp = is_array($snapshotView['bom'] ?? null) ? $snapshotView['bom'] : [];
            $basePricingCmp = is_array($baseSnapshotView['pricing'] ?? null) ? $baseSnapshotView['pricing'] : [];
            $newPricingCmp = is_array($snapshotView['pricing'] ?? null) ? $snapshotView['pricing'] : [];
            $baseTotalsCmp = is_array($baseSnapshotView['totals'] ?? null) ? $baseSnapshotView['totals'] : [];
            $newTotalsCmp = is_array($snapshotView['totals'] ?? null) ? $snapshotView['totals'] : [];
        @endphp
        <div style="margin-top:12px;">
            <h3 style="margin-bottom:8px;">概要比較（編集前 / 編集後）</h3>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <div style="flex:1 1 420px; border:1px solid #e5e7eb; border-radius:8px; padding:10px; background:#f9fafb;">
                    <div style="font-weight:700; margin-bottom:8px;">編集前（初版）</div>
                    <table>
                        <tbody>
                            <tr><th>MFD数</th><td>{{ $baseConfigCmp['mfdCount'] ?? '-' }}</td></tr>
                            <tr><th>チューブ数</th><td>{{ $baseConfigCmp['tubeCount'] ?? '-' }}</td></tr>
                            <tr><th>エラー件数</th><td>{{ count($baseErrorsCmp) }}</td></tr>
                            <tr><th>BOM件数</th><td>{{ count($baseBomCmp) }}</td></tr>
                            <tr><th>価格内訳件数</th><td>{{ count($basePricingCmp) }}</td></tr>
                            {{-- <tr><th>小計</th><td>{{ $baseTotalsCmp['subtotal'] ?? '-' }}</td></tr>
                            <tr><th>税</th><td>{{ $baseTotalsCmp['tax'] ?? '-' }}</td></tr> --}}
                            <tr><th>合計</th><td>{{ $baseTotalsCmp['total'] ?? '-' }}</td></tr>
                            <tr><th>作成者アカウント表示名</th><td>{{ $requestedByLabel }}</td></tr>
                            <tr><th>担当者</th><td>{{ $req->requested_by_assignee_name ?? '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div style="flex:1 1 420px; border:1px solid #e5e7eb; border-radius:8px; padding:10px; background:#f9fafb;">
                    <div style="font-weight:700; margin-bottom:8px;">編集後（申請内容）</div>
                    <table>
                        <tbody>
                            <tr><th>MFD数</th><td>{{ $newConfigCmp['mfdCount'] ?? '-' }}</td></tr>
                            <tr><th>チューブ数</th><td>{{ $newConfigCmp['tubeCount'] ?? '-' }}</td></tr>
                            <tr><th>エラー件数</th><td>{{ count($newErrorsCmp) }}</td></tr>
                            <tr><th>BOM件数</th><td>{{ count($newBomCmp) }}</td></tr>
                            <tr><th>価格内訳件数</th><td>{{ count($newPricingCmp) }}</td></tr>
                            {{-- <tr><th>小計</th><td>{{ $newTotalsCmp['subtotal'] ?? '-' }}</td></tr>
                            <tr><th>税</th><td>{{ $newTotalsCmp['tax'] ?? '-' }}</td></tr> --}}
                            <tr><th>合計</th><td>{{ $newTotalsCmp['total'] ?? '-' }}</td></tr>
                            <tr><th>作成者アカウント表示名</th><td>{{ $requestedByLabel }}</td></tr>
                            <tr><th>担当者</th><td>{{ $req->requested_by_assignee_name ?? '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($baseSvg))
        @include('partials.snapshot_bundle', [
            'panelTitle' => '初版（申請時点の現行版）',
            'pdfUrl' => $baseSnapshotPdfUrl,
            'summaryItems' => [
                ['label' => '対象', 'value' => $req->entity_type.' #'.$req->entity_id],
                ['label' => 'ステータス', 'value' => $req->status],
                ['label' => '版作成者', 'value' => $requestedByLabel],
                ['label' => '担当者', 'value' => $req->requested_by_assignee_name ?? '-'],
            ],
            'showMemoCard' => true,
            'memoValue' => $baseMemo,
            'memoReadonly' => true,
            'memoLabel' => 'メモ',
            'showCreatorColumns' => false,
            'svg' => $baseSvg,
            'snapshot' => $baseSnapshotView,
            'config' => $baseConfig,
            'derived' => $baseDerived,
            'errors' => $baseErrors,
            'snapshotJson' => json_encode($baseSnapshotView, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'configJson' => $baseConfigJson,
            'derivedJson' => $baseDerivedJson,
            'errorsJson' => $baseErrorsJson,
        ])
    @endif

    @include('partials.snapshot_bundle', [
        'panelTitle' => '申請内容（新しい版）',
        'pdfUrl' => $snapshotPdfUrl,
        'summaryItems' => [
            ['label' => '対象', 'value' => $req->entity_type.' #'.$req->entity_id],
            ['label' => 'ステータス', 'value' => $req->status],
            ['label' => '申請者', 'value' => $req->requested_by_account_display_name ?? ('ID: '.$req->requested_by)],
            ['label' => '承認者', 'value' => $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-')],
            ['label' => '版作成者', 'value' => $requestedByLabel],
            ['label' => 'コメント', 'value' => $req->comment ?? '（なし）'],
            ['label' => '担当者', 'value' => $req->requested_by_assignee_name ?? '-'],
        ],
        'showMemoCard' => true,
        'memoValue' => ($requestMemo !== '' ? $requestMemo : ($req->memo ?? '')),
        'memoUpdateUrl' => $memoUpdateUrl ?? null,
        'memoButtonLabel' => 'メモ保存',
        'memoReadonly' => true,
        'memoLabel' => 'メモ',
        'showCreatorColumns' => false,
        'svg' => $svg,
        'snapshot' => $snapshotView,
        'config' => $config,
        'derived' => $derived,
        'errors' => $errors,
        'snapshotJson' => json_encode($snapshotView, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'configJson' => $configJson,
        'derivedJson' => $derivedJson,
        'errorsJson' => $errorsJson,
    ])
@endsection
