@extends('work.layout')

@section('content')
    @php
        $sourceTypeLabels = [
            'quote' => '見積',
            'change_request' => '変更申請',
            'configurator' => 'コンフィギュレーター',
            'session' => 'セッション',
        ];
    @endphp
    @php
        $currentRun = null;
        foreach ($runs as $candidateRun) {
            if (!empty($candidateRun['is_current_version'])) {
                $currentRun = $candidateRun;
                break;
            }
        }
    @endphp
    <h1>見積 #{{ $quote->id ?? '' }} 計算履歴</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.quotes.show', $quote->id) }}">見積詳細へ戻る</a>
    </div>

    @if(is_array($currentRun))
        <div style="margin:8px 0 12px; padding:10px; border:1px solid #86efac; border-radius:8px; background:#f0fdf4;">
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <span style="display:inline-block; padding:2px 7px; border-radius:999px; border:1px solid #10b981; background:#d1fae5; color:#065f46; font-size:11px; font-weight:700;">現行版</span>
                <strong>履歴 #{{ $currentRun['run_no'] ?? '-' }}</strong>
                <span>{{ $currentRun['event_label'] ?? ($currentRun['event_type'] ?? '-') }}</span>
                <span class="muted">{{ $currentRun['created_at'] ?? '-' }}</span>
            </div>
            <div class="muted" style="margin-top:6px;">承認待ち・却下の変更申請履歴ではなく、実際に見積へ反映済みの最新履歴を現行版として表示しています。</div>
        </div>
    @endif

    @if(!$canExpandCalcRuns)
        <div class="muted" style="margin:8px 0;">顧客権限では重要イベントのみ表示します。</div>
    @endif

    <table>
        <thead>
            <tr>
                <th>履歴番号</th>
                <th>版状態</th>
                <th>イベント</th>
                <th>発生元</th>
                <th>小計（元値）</th>
                <th>丸め単価</th>
                <th>再計算合計</th>
                <th>調整後</th>
                <th>税率</th>
                <th>税</th>
                <th>総合計</th>
                <th>実行者</th>
                <th>日時</th>
            </tr>
        </thead>
        <tbody>
            @forelse($runs as $run)
                @php
                    $tone = trim((string)($run['version_state_tone'] ?? 'neutral'));
                    $pillStyle = match ($tone) {
                        'current' => 'display:inline-block; padding:2px 7px; border-radius:999px; border:1px solid #10b981; background:#d1fae5; color:#065f46; font-size:11px; font-weight:700;',
                        'pending' => 'display:inline-block; padding:2px 7px; border-radius:999px; border:1px solid #f59e0b; background:#fef3c7; color:#92400e; font-size:11px; font-weight:700;',
                        'rejected' => 'display:inline-block; padding:2px 7px; border-radius:999px; border:1px solid #ef4444; background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700;',
                        'historical' => 'display:inline-block; padding:2px 7px; border-radius:999px; border:1px solid #cbd5e1; background:#f1f5f9; color:#334155; font-size:11px; font-weight:700;',
                        default => 'display:inline-block; padding:2px 7px; border-radius:999px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; font-size:11px; font-weight:700;',
                    };
                @endphp
                <tr @if(!empty($run['is_current_version'])) style="background:#ecfdf5;" @endif>
                    <td>{{ $run['run_no'] ?? '-' }}</td>
                    <td>
                        @if(!empty($run['version_state_label']))
                            <span style="{{ $pillStyle }}">{{ $run['version_state_label'] }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $run['event_label'] ?? ($run['event_type'] ?? '-') }}</td>
                    <td>
                        @php
                            $sourceType = $run['source_type'] ?? null;
                            $sourceId = $run['source_id'] ?? null;
                            $sourceTypeKey = strtolower((string)$sourceType);
                            $sourceTypeLabel = $sourceType ? ($sourceTypeLabels[$sourceTypeKey] ?? 'その他') : null;
                        @endphp
                        @if($sourceTypeLabel)
                            {{ $sourceTypeLabel }}@if($sourceId !== null) #{{ $sourceId }}@endif
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ format_amount($run['subtotal_raw'] ?? null) }}</td>
                    <td>{{ format_amount($run['unit_price_rounded'] ?? null) }}</td>
                    <td>{{ format_amount($run['recomputed_total'] ?? null) }}</td>
                    <td>{{ format_amount($run['adjusted_total'] ?? null) }}</td>
                    <td>{{ $run['tax_rate'] ?? '-' }}</td>
                    <td>{{ format_amount($run['tax_amount'] ?? null) }}</td>
                    <td>{{ format_amount($run['grand_total'] ?? null) }}</td>
                    <td>{{ $run['triggered_by_name'] ?? ($run['triggered_by_email'] ?? '-') }}</td>
                    <td>{{ $run['created_at'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13">履歴はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="margin-top:14px;">各履歴の計算内訳</h2>
    @forelse($runs as $run)
        @php
            $runNo = $run['run_no'] ?? '-';
            $eventLabel = $run['event_label'] ?? ($run['event_type'] ?? '-');
            $createdAt = $run['created_at'] ?? '-';
            $runInput = is_array($run['input'] ?? null) ? $run['input'] : [];
            $runSteps = is_array($run['steps'] ?? null) ? $run['steps'] : [];
            $runOutput = is_array($run['output'] ?? null) ? $run['output'] : [];
            $runContext = is_array($run['context'] ?? null) ? $run['context'] : [];
            $isCurrentVersion = !empty($run['is_current_version']);
            $versionStateLabel = $run['version_state_label'] ?? null;
            $versionTone = trim((string)($run['version_state_tone'] ?? 'neutral'));
            $badgeStyle = match ($versionTone) {
                'current' => 'display:inline-block; margin-left:6px; padding:2px 7px; border-radius:999px; border:1px solid #10b981; background:#d1fae5; color:#065f46; font-size:11px; font-weight:700;',
                'pending' => 'display:inline-block; margin-left:6px; padding:2px 7px; border-radius:999px; border:1px solid #f59e0b; background:#fef3c7; color:#92400e; font-size:11px; font-weight:700;',
                'rejected' => 'display:inline-block; margin-left:6px; padding:2px 7px; border-radius:999px; border:1px solid #ef4444; background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700;',
                'historical' => 'display:inline-block; margin-left:6px; padding:2px 7px; border-radius:999px; border:1px solid #cbd5e1; background:#f1f5f9; color:#334155; font-size:11px; font-weight:700;',
                default => 'display:inline-block; margin-left:6px; padding:2px 7px; border-radius:999px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; font-size:11px; font-weight:700;',
            };
        @endphp
        <details style="margin-top:8px; border:1px solid #d1d5db; border-radius:8px; background:@if($isCurrentVersion) #f0fdf4 @else #fff @endif; padding:8px;" @if($isCurrentVersion || ($currentRun === null && $loop->first)) open @endif>
            <summary>
                履歴 #{{ $runNo }} / {{ $eventLabel }} / {{ $createdAt }}
                @if($versionStateLabel)
                    <span style="{{ $badgeStyle }}">{{ $versionStateLabel }}</span>
                @endif
            </summary>

            @include('work.quotes._pricing_breakdown', [
                'sectionTitle' => '',
                'pricingInput' => $runInput,
                'pricingSteps' => $runSteps,
                'pricingOutput' => $runOutput,
                'localized' => true,
                'showRawJson' => false,
                'context' => array_merge($runContext, [
                    'run_id' => (int)($run['id'] ?? 0),
                    'source_type' => $run['source_type'] ?? null,
                    'source_id' => $run['source_id'] ?? null,
                ]),
            ])
        </details>
    @empty
        <div class="muted" style="margin-top:8px;">表示可能な計算内訳はありません。</div>
    @endforelse
@endsection
