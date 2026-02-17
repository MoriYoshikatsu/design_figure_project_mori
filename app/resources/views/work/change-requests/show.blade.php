@extends('work.layout')

@section('content')
    @php
        $canApprove = $canApprove ?? true;
        $operation = strtoupper((string)($req->operation ?? 'UPDATE'));
        $isSnapshotEntity = (bool)($isSnapshotEntity ?? false);
        $requestHeading = $requestHeading ?? ((string)($req->entity_type ?? '-') . ' / ' . $operation);
        $entityTypeDisplay = $entityTypeLabel ?? (string)($req->entity_type ?? '-');
        $operationDisplay = $operationLabel ?? $operation;
        $statusDisplay = $statusLabel ?? (string)($req->status ?? '-');
        $targetDisplay = $targetLabel ?? ((int)($req->entity_id ?? 0) > 0 ? ('#' . (int)$req->entity_id) : '-');

        $snapshotPdfUrl = $snapshotPdfUrl ?? route('work.change-requests.snapshot.pdf', $req->id);
        $baseSnapshotPdfUrl = $baseSnapshotPdfUrl ?? route('work.change-requests.snapshot-base.pdf', $req->id);
        $compareSnapshotPdfUrl = $compareSnapshotPdfUrl ?? route('work.change-requests.snapshot-compare.pdf', $req->id);

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
            $requestedByLabel = $req->requested_by ? ('ID: ' . $req->requested_by) : '-';
        }
        if ($requestedByRole !== '') {
            $requestedByLabel .= ' (' . strtoupper($requestedByRole) . ')';
        }

        $changeRows = is_array($changeRows ?? null) ? $changeRows : [];
        $changedCount = (int)($changedCount ?? 0);
        $changeContextItems = is_array($changeContextItems ?? null) ? $changeContextItems : [];
        $formatValue = static function ($value): string {
            if ($value === null) return '∅';
            if (is_bool($value)) return $value ? 'true' : 'false';
            if (is_scalar($value)) return (string)$value;
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            return $encoded === false ? '[unserializable]' : $encoded;
        };
    @endphp

    <h1>リクエスト #{{ $req->id ?? '' }} 詳細</h1>
    <div class="muted" style="margin:4px 0 12px;">{{ $requestHeading }}</div>

    @if($canApprove && $req->status === 'PENDING')
        <div class="actions" style="margin:12px 0;">
            <form method="POST" action="{{ route('work.change-requests.approve', $req->id) }}">
                @csrf
                <button type="submit">承認</button>
            </form>
            <form method="POST" action="{{ route('work.change-requests.reject', $req->id) }}">
                @csrf
                <button type="submit">却下</button>
            </form>
        </div>
    @endif

    <h3>申請概要</h3>
    <table>
        <tbody>
            <tr><th>対象種別</th><td>{{ $entityTypeDisplay }}</td></tr>
            <tr><th>操作</th><td>{{ $operationDisplay }}</td></tr>
            <tr><th>ステータス</th><td>{{ $statusDisplay }} ({{ $req->status }})</td></tr>
            <tr><th>対象ID</th><td>{{ $targetDisplay }}</td></tr>
            <tr><th>申請者</th><td>{{ $requestedByLabel }}</td></tr>
            <tr><th>承認者</th><td>{{ $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-') }}</td></tr>
            <tr><th>担当者</th><td>{{ $req->requested_by_assignee_name ?? '-' }}</td></tr>
            <tr><th>コメント</th><td>{{ $req->comment ?? '（なし）' }}</td></tr>
            <tr><th>メモ</th><td>{{ $req->memo ?? '（なし）' }}</td></tr>
            <tr><th>申請日時</th><td>{{ $req->created_at }}</td></tr>
            <tr><th>更新日時</th><td>{{ $req->updated_at }}</td></tr>
            <tr><th>承認日時</th><td>{{ $req->approved_at ?? '-' }}</td></tr>
        </tbody>
    </table>

    @if(!empty($changeContextItems))
        <h3 style="margin-top:12px;">関連情報</h3>
        <table>
            <tbody>
            @foreach($changeContextItems as $item)
                <tr>
                    <th>{{ $item['label'] }}</th>
                    <td>{{ $item['value'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(!$isSnapshotEntity)
        <h3 style="margin-top:12px;">変更内容</h3>
        <div class="muted" style="margin:6px 0;">
            変更フィールド数: {{ $changedCount }} / {{ count($changeRows) }}
        </div>

        @if(empty($changeRows))
            <div class="muted">変更内容を解析できませんでした。下部のJSONを確認してください。</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>項目</th>
                        <th>変更前</th>
                        <th>変更後</th>
                        <th>差分</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($changeRows as $row)
                    <tr @if(!empty($row['changed'])) style="background:#fffbeb;" @endif>
                        <td>{{ $row['path'] ?? '' }}</td>
                        <td><pre style="margin:0; white-space:pre-wrap; word-break:break-all;">{{ $formatValue($row['before'] ?? null) }}</pre></td>
                        <td><pre style="margin:0; white-space:pre-wrap; word-break:break-all;">{{ $formatValue($row['after'] ?? null) }}</pre></td>
                        <td>{{ !empty($row['changed']) ? '変更あり' : '変更なし' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if($isSnapshotEntity)
        <div style="margin-top:12px;">
            <a href="{{ $compareSnapshotPdfUrl }}">比較PDFダウンロード（編集前/編集後）</a>
        </div>

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
                                <tr><th>合計</th><td>{{ $baseTotalsCmp['total'] ?? '-' }}</td></tr>
                                <tr><th>作成アカウント</th><td>{{ $requestedByLabel }}</td></tr>
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
                                <tr><th>合計</th><td>{{ $newTotalsCmp['total'] ?? '-' }}</td></tr>
                                <tr><th>作成アカウント</th><td>{{ $requestedByLabel }}</td></tr>
                                <tr><th>担当者</th><td>{{ $req->requested_by_assignee_name ?? '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if(!empty($baseSvg))
            @include('snapshot_bundle', [
                'panelTitle' => '初版（申請時点の現行版）',
                'pdfUrl' => $baseSnapshotPdfUrl,
                'summaryItems' => [
                    ['label' => '対象', 'value' => $entityTypeDisplay . ' ' . $targetDisplay],
                    ['label' => '操作', 'value' => $operationDisplay],
                    ['label' => 'ステータス', 'value' => $statusDisplay],
                    ['label' => '作成アカウント', 'value' => $requestedByLabel],
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

        @include('snapshot_bundle', [
            'panelTitle' => '申請内容（新しい版）',
            'pdfUrl' => $snapshotPdfUrl,
            'summaryItems' => [
                ['label' => '対象', 'value' => $entityTypeDisplay . ' ' . $targetDisplay],
                ['label' => '操作', 'value' => $operationDisplay],
                ['label' => 'ステータス', 'value' => $statusDisplay],
                ['label' => '作成アカウント', 'value' => $requestedByLabel],
                ['label' => '担当者', 'value' => $req->requested_by_assignee_name ?? '-'],
                ['label' => '申請者', 'value' => $req->requested_by_account_display_name ?? ('ID: '.$req->requested_by)],
                ['label' => '承認者', 'value' => $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-')],
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
    @endif

    <details style="margin-top:12px;">
        <summary>申請ペイロード(JSON)</summary>
        <pre style="white-space:pre-wrap; word-break:break-all;">{{ $proposedJson }}</pre>
    </details>
@endsection
