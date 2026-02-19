@extends('work.layout')

@section('content')
    @php
        $canApprove = $canApprove ?? true;
        $operation = strtoupper((string)($req->operation ?? 'UPDATE'));
        $entityTypeRaw = strtolower((string)($req->entity_type ?? ''));
        $isSnapshotEntity = (bool)($isSnapshotEntity ?? false);
        $requestHeading = $requestHeading ?? ((string)($req->entity_type ?? '-') . ' / ' . $operation);
        $entityTypeDisplay = $entityTypeLabel ?? (string)($req->entity_type ?? '-');
        $operationDisplay = $operationLabel ?? $operation;
        $statusDisplay = $statusLabel ?? (string)($req->status ?? '-');
        $targetDisplay = $targetLabel ?? ((int)($req->entity_id ?? 0) > 0 ? ('#' . (int)$req->entity_id) : '-');
        $isDeleteOperation = $operation === 'DELETE';
        $isAccountRelated = $entityTypeRaw === 'account' || str_starts_with($entityTypeRaw, 'account_');

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
        $requestAccountLabel = trim((string)($req->request_account_display_name ?? ''));
        if ($requestAccountLabel === '') {
            $requestAccountLabel = $requestedByLabel;
        }
        $requestAccountEmail = trim((string)($req->request_account_email ?? $req->requested_by_email ?? ''));
        if ($requestAccountEmail === '') {
            $requestAccountEmail = '-';
        }
        $requestAccountAssignee = trim((string)($req->request_account_assignee_name ?? $req->requested_by_assignee_name ?? ''));
        if ($requestAccountAssignee === '') {
            $requestAccountAssignee = '-';
        }
        $summaryFieldLabels = [
            'quote_id' => '見積ID',
            'status' => 'ステータス',
            'account_internal_name' => 'accounts.internal_name',
            'account_user_name' => 'users.name',
            'assignee_name' => '担当者',
            'customer_emails' => '登録メールアドレス',
            'request_count' => '承認変更申請件数',
            'template_version_id' => 'ルールテンプレ',
            'price_book_id' => '納品物価格表',
            'subtotal' => '小計',
            'tax' => '税',
            'total' => '合計',
        ];
        $summaryDefaultFields = [
            'quote_id',
            'status',
            'account_internal_name',
            'account_user_name',
            'assignee_name',
            'customer_emails',
            'request_count',
            'template_version_id',
            'price_book_id',
            'subtotal',
            'tax',
            'total',
        ];
        $selectedSummaryFieldLabels = [];
        if ((string)($req->entity_type ?? '') === 'quote') {
            $summaryFieldsRaw = is_array($snapshotView['summary_card_fields'] ?? null)
                ? $snapshotView['summary_card_fields']
                : (
                    is_array($baseSnapshotView['summary_card_fields'] ?? null)
                        ? $baseSnapshotView['summary_card_fields']
                        : $summaryDefaultFields
                );
            foreach ($summaryFieldsRaw as $field) {
                $field = (string)$field;
                if ($field === 'account_display_name') {
                    $field = 'account_internal_name';
                }
                $label = $summaryFieldLabels[$field] ?? null;
                if (!$label) {
                    continue;
                }
                if (!in_array($label, $selectedSummaryFieldLabels, true)) {
                    $selectedSummaryFieldLabels[] = $label;
                }
            }
        }
        $quoteSummaryContext = is_array($quoteSummaryContext ?? null) ? $quoteSummaryContext : [];
        $buildQuoteSummaryItems = static function (array $snapshotData, array $context, array $labels): array {
            $defaultFields = [
                'quote_id',
                'status',
                'account_internal_name',
                'account_user_name',
                'assignee_name',
                'customer_emails',
                'request_count',
                'template_version_id',
                'price_book_id',
                'subtotal',
                'tax',
                'total',
            ];
            $fieldsRaw = is_array($snapshotData['summary_card_fields'] ?? null) ? $snapshotData['summary_card_fields'] : $defaultFields;
            $fields = [];
            foreach ($fieldsRaw as $field) {
                $field = (string)$field;
                if ($field === 'account_display_name') {
                    $field = 'account_internal_name';
                }
                if (!array_key_exists($field, $labels)) {
                    continue;
                }
                if (!in_array($field, $fields, true)) {
                    $fields[] = $field;
                }
            }
            if (empty($fields)) {
                $fields = $defaultFields;
            }

            $totals = is_array($snapshotData['totals'] ?? null) ? $snapshotData['totals'] : [];
            $valueMap = [
                'quote_id' => $context['quote_id'] ?? '',
                'status' => $context['status'] ?? '',
                'account_internal_name' => $context['account_internal_name'] ?? '-',
                'account_user_name' => $context['account_user_name'] ?? '-',
                'assignee_name' => $context['assignee_name'] ?? '-',
                'customer_emails' => $context['customer_emails'] ?? '-',
                'request_count' => $context['request_count'] ?? 0,
                'template_version_id' => $snapshotData['template_version_id'] ?? '',
                'price_book_id' => $snapshotData['price_book_id'] ?? '',
                'subtotal' => $totals['subtotal'] ?? '',
                'tax' => $totals['tax'] ?? '',
                'total' => $totals['total'] ?? '',
            ];

            $items = [];
            foreach ($fields as $field) {
                $items[] = [
                    'label' => $labels[$field] ?? $field,
                    'value' => $valueMap[$field] ?? '',
                ];
            }
            return $items;
        };
        $baseQuoteSummaryItems = [];
        $newQuoteSummaryItems = [];
        if ((string)($req->entity_type ?? '') === 'quote') {
            $baseQuoteSummaryItems = $buildQuoteSummaryItems($baseSnapshotView, $quoteSummaryContext, $summaryFieldLabels);
            $newQuoteSummaryItems = $buildQuoteSummaryItems($snapshotView, $quoteSummaryContext, $summaryFieldLabels);
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

    <style>
        .req-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.4;
            margin-right: 4px;
            border: 1px solid transparent;
        }
        .req-pill-delete {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }
        .req-pill-account {
            background: #e0e7ff;
            color: #3730a3;
            border-color: #a5b4fc;
        }
    </style>

    <h1>承認変更申請 #{{ $req->id ?? '' }}</h1>
    {{-- <div class="muted" style="margin:4px 0 12px;">{{ $requestHeading }}</div> --}}

    @if($isDeleteOperation || $isAccountRelated)
        <div style="margin:8px 0 12px; padding:10px; border-radius:8px; border:1px solid @if($isDeleteOperation) #fca5a5 @else #a5b4fc @endif; background:@if($isDeleteOperation) #fef2f2 @else #eef2ff @endif;">
            @if($isDeleteOperation)
                <span class="req-pill req-pill-delete">DELETE</span>
                削除操作の承認変更申請です。対象の取り扱いに注意してください。
            @endif
            @if($isAccountRelated)
                <span class="req-pill req-pill-account">アカウント系</span>
                アカウント/権限関連の承認変更申請です。
            @endif
        </div>
    @endif

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

    {{-- <h3>申請概要</h3> --}}
    <table>
        <tbody>
            <tr><th>ステータス</th><td>{{ $statusDisplay }} ({{ $req->status }})</td></tr>
            <tr><th>操作</th><td>@if($isDeleteOperation)<span class="req-pill req-pill-delete">DELETE</span>@endif{{ $operationDisplay }}</td></tr>
            <tr><th>対象種別</th><td>@if($isAccountRelated)<span class="req-pill req-pill-account">アカウント系</span>@endif{{ $entityTypeDisplay }}</td></tr>
            <tr><th>対象ID</th><td>{{ $targetDisplay }}</td></tr>
            <tr><th>申請対象作成者</th><td>{{ $requestAccountLabel }}</td></tr>
            <tr><th>登録メールアドレス</th><td>{{ $requestAccountEmail }}</td></tr>
            <tr><th>担当者</th><td>{{ $requestAccountAssignee }}</td></tr>
            <tr><th>メモ</th><td>{{ $req->memo ?? '（なし）' }}</td></tr>
            <tr><th>申請者</th><td>{{ $requestedByLabel }}</td></tr>
            <tr><th>承認者</th><td>{{ $req->approved_by_account_display_name ?? ($req->approved_by ? 'ID: '.$req->approved_by : '-') }}</td></tr>
            @if(!empty($selectedSummaryFieldLabels))
                <tr><th>概要カード表示項目</th><td>{{ implode(' / ', $selectedSummaryFieldLabels) }}</td></tr>
            @endif
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
                                <tr><th>作成アカウント</th><td>{{ $requestAccountLabel }}</td></tr>
                                <tr><th>担当者</th><td>{{ $requestAccountAssignee }}</td></tr>
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
                                <tr><th>作成アカウント</th><td>{{ $requestAccountLabel }}</td></tr>
                                <tr><th>担当者</th><td>{{ $requestAccountAssignee }}</td></tr>
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
                'includeAutoSummary' => false,
                'summaryItems' => ((string)($req->entity_type ?? '') === 'quote' && !empty($baseQuoteSummaryItems))
                    ? $baseQuoteSummaryItems
                    : [
                        ['label' => '対象', 'value' => $entityTypeDisplay . ' ' . $targetDisplay],
                        ['label' => '操作', 'value' => $operationDisplay],
                        ['label' => 'ステータス', 'value' => $statusDisplay],
                        ['label' => '作成アカウント', 'value' => $requestAccountLabel],
                        ['label' => '登録メールアドレス', 'value' => $requestAccountEmail],
                        ['label' => '担当者', 'value' => $requestAccountAssignee],
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
            'includeAutoSummary' => false,
            'summaryItems' => ((string)($req->entity_type ?? '') === 'quote' && !empty($newQuoteSummaryItems))
                ? $newQuoteSummaryItems
                : [
                    ['label' => '対象', 'value' => $entityTypeDisplay . ' ' . $targetDisplay],
                    ['label' => '操作', 'value' => $operationDisplay],
                    ['label' => 'ステータス', 'value' => $statusDisplay],
                    ['label' => '作成アカウント', 'value' => $requestAccountLabel],
                    ['label' => '登録メールアドレス', 'value' => $requestAccountEmail],
                    ['label' => '担当者', 'value' => $requestAccountAssignee],
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
