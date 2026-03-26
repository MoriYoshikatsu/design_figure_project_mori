@php
    $quoteId = (int)($quoteId ?? 0);
    $importantRuns = is_array($importantRuns ?? null) ? $importantRuns : [];
    $allRuns = is_array($allRuns ?? null) ? $allRuns : [];
    $canExpandAll = (bool)($canExpandAll ?? false);
    $highlightSourceType = isset($highlightSourceType) ? (string)$highlightSourceType : null;
    $highlightSourceId = isset($highlightSourceId) && is_numeric((string)$highlightSourceId) ? (int)$highlightSourceId : null;
    $drawerSuffix = trim((string)($drawerSuffix ?? 'default'));
    $drawerId = 'calc-history-drawer-' . $quoteId . '-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $drawerSuffix);
    $backdropId = $drawerId . '-backdrop';
    $drawerTitle = trim((string)($drawerTitle ?? '計算履歴'));
    $canOpenPage = $quoteId > 0;
    $currentRun = null;
    foreach (($allRuns !== [] ? $allRuns : $importantRuns) as $candidateRun) {
        if (!empty($candidateRun['is_current_version'])) {
            $currentRun = $candidateRun;
            break;
        }
    }
@endphp

@once
    <style>
        .calc-history-drawer {
            position: fixed;
            top: 0;
            right: 0;
            width: min(640px, 92vw);
            height: 100vh;
            background: #fff;
            border-left: 1px solid #d1d5db;
            box-shadow: -8px 0 24px rgba(15, 23, 42, 0.16);
            z-index: 1001;
            display: flex;
            flex-direction: column;
        }
        .calc-history-drawer[hidden] {
            display: none;
        }
        .calc-history-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.42);
            z-index: 1000;
        }
        .calc-history-backdrop[hidden] {
            display: none;
        }
        .calc-history-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .calc-history-body {
            overflow-y: auto;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .calc-history-table th,
        .calc-history-table td {
            font-size: 13px;
        }
        .calc-history-row-highlight {
            background: #fffbeb;
        }
        .calc-history-row-current {
            background: #ecfdf5;
        }
        .calc-history-pill {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            font-size: 11px;
            font-weight: 700;
            background: #f8fafc;
        }
        .calc-history-pill-current {
            border-color: #10b981;
            background: #d1fae5;
            color: #065f46;
        }
        .calc-history-pill-pending {
            border-color: #f59e0b;
            background: #fef3c7;
            color: #92400e;
        }
        .calc-history-pill-rejected {
            border-color: #ef4444;
            background: #fee2e2;
            color: #991b1b;
        }
        .calc-history-pill-historical {
            border-color: #cbd5e1;
            background: #f1f5f9;
            color: #334155;
        }
    </style>
@endonce

<div class="actions" style="margin:8px 0;">
    <button type="button" data-calc-history-open="{{ $drawerId }}">{{ $drawerTitle }}</button>
    @if($canOpenPage)
        <a href="{{ route('work.quotes.calc-runs', $quoteId) }}">全履歴ページ</a>
    @endif
</div>

<div id="{{ $backdropId }}" class="calc-history-backdrop" hidden data-calc-history-close="{{ $drawerId }}"></div>
<div id="{{ $drawerId }}" class="calc-history-drawer" hidden>
    <div class="calc-history-head">
        <strong>{{ $drawerTitle }}</strong>
        <button type="button" data-calc-history-close="{{ $drawerId }}">閉じる</button>
    </div>
    <div class="calc-history-body">
        @if(is_array($currentRun))
            <div style="padding:10px; border:1px solid #86efac; border-radius:8px; background:#f0fdf4;">
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="calc-history-pill calc-history-pill-current">現行版</span>
                    <strong>Run #{{ $currentRun['run_no'] ?? '-' }}</strong>
                    <span>{{ $currentRun['event_label'] ?? ($currentRun['event_type'] ?? '-') }}</span>
                    <span class="muted">{{ $currentRun['created_at'] ?? '-' }}</span>
                </div>
                <div class="muted" style="margin-top:6px;">承認待ち・却下の変更申請履歴ではなく、実際に見積へ反映済みの最新履歴を現行版として表示しています。</div>
            </div>
        @endif

        <div>
            <h3 style="margin:0 0 8px;">重要イベント要約</h3>
            <table class="calc-history-table">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>版状態</th>
                        <th>イベント</th>
                        <th>小計(税前)</th>
                        <th>税</th>
                        <th>総合計</th>
                        <th>実行者</th>
                        <th>日時</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($importantRuns as $run)
                        @php
                            $isHighlight = $highlightSourceType !== null
                                && $highlightSourceId !== null
                                && (string)($run['source_type'] ?? '') === $highlightSourceType
                                && (int)($run['source_id'] ?? 0) === $highlightSourceId;
                            $isCurrentVersion = !empty($run['is_current_version']);
                            $versionTone = trim((string)($run['version_state_tone'] ?? 'neutral'));
                            $versionToneClass = match ($versionTone) {
                                'current' => 'calc-history-pill-current',
                                'pending' => 'calc-history-pill-pending',
                                'rejected' => 'calc-history-pill-rejected',
                                'historical' => 'calc-history-pill-historical',
                                default => '',
                            };
                            $rowClass = $isCurrentVersion
                                ? 'calc-history-row-current'
                                : ($isHighlight ? 'calc-history-row-highlight' : '');
                        @endphp
                        <tr @if($rowClass !== '') class="{{ $rowClass }}" @endif>
                            <td>
                                {{ $run['run_no'] ?? '-' }}
                                @if($isHighlight)
                                    <span class="calc-history-pill">対象</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($run['version_state_label']))
                                    <span class="calc-history-pill {{ $versionToneClass }}">{{ $run['version_state_label'] }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $run['event_label'] ?? ($run['event_type'] ?? '-') }}</td>
                            <td>{{ format_amount($run['adjusted_total'] ?? null) }}</td>
                            <td>{{ format_amount($run['tax_amount'] ?? null) }}</td>
                            <td>{{ format_amount($run['grand_total'] ?? null) }}</td>
                            <td>{{ $run['triggered_by_name'] ?? ($run['triggered_by_email'] ?? '-') }}</td>
                            <td>{{ $run['created_at'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">履歴はありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($canExpandAll)
            <details>
                <summary>全ラン展開（Admin/Sales）</summary>
                <div style="margin-top:8px;">
                    <table class="calc-history-table">
                        <thead>
                            <tr>
                                <th>Run</th>
                                <th>版状態</th>
                                <th>イベント</th>
                                <th>operation</th>
                                <th>小計Raw</th>
                                <th>丸め単価</th>
                                <th>再計算合計</th>
                                <th>調整後</th>
                                <th>税率</th>
                                <th>税</th>
                                <th>総合計</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allRuns as $run)
                                @php
                                    $isHighlight = $highlightSourceType !== null
                                        && $highlightSourceId !== null
                                        && (string)($run['source_type'] ?? '') === $highlightSourceType
                                        && (int)($run['source_id'] ?? 0) === $highlightSourceId;
                                    $isCurrentVersion = !empty($run['is_current_version']);
                                    $versionTone = trim((string)($run['version_state_tone'] ?? 'neutral'));
                                    $versionToneClass = match ($versionTone) {
                                        'current' => 'calc-history-pill-current',
                                        'pending' => 'calc-history-pill-pending',
                                        'rejected' => 'calc-history-pill-rejected',
                                        'historical' => 'calc-history-pill-historical',
                                        default => '',
                                    };
                                    $rowClass = $isCurrentVersion
                                        ? 'calc-history-row-current'
                                        : ($isHighlight ? 'calc-history-row-highlight' : '');
                                @endphp
                                <tr @if($rowClass !== '') class="{{ $rowClass }}" @endif>
                                    <td>{{ $run['run_no'] ?? '-' }}</td>
                                    <td>
                                        @if(!empty($run['version_state_label']))
                                            <span class="calc-history-pill {{ $versionToneClass }}">{{ $run['version_state_label'] }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $run['event_label'] ?? ($run['event_type'] ?? '-') }}</td>
                                    <td>{{ ($run['context']['operation'] ?? '-') }}</td>
                                    <td>{{ format_amount($run['subtotal_raw'] ?? null) }}</td>
                                    <td>{{ format_amount($run['unit_price_rounded'] ?? null) }}</td>
                                    <td>{{ format_amount($run['recomputed_total'] ?? null) }}</td>
                                    <td>{{ format_amount($run['adjusted_total'] ?? null) }}</td>
                                    <td>{{ $run['tax_rate'] ?? '-' }}</td>
                                    <td>{{ format_amount($run['tax_amount'] ?? null) }}</td>
                                    <td>{{ format_amount($run['grand_total'] ?? null) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11">履歴はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>
</div>

@once
    <script>
        document.addEventListener('click', function (event) {
            const openTarget = event.target.closest('[data-calc-history-open]');
            if (openTarget) {
                const id = openTarget.getAttribute('data-calc-history-open');
                const drawer = document.getElementById(id);
                const backdrop = document.getElementById(id + '-backdrop');
                if (drawer) drawer.hidden = false;
                if (backdrop) backdrop.hidden = false;
                return;
            }

            const closeTarget = event.target.closest('[data-calc-history-close]');
            if (closeTarget) {
                const id = closeTarget.getAttribute('data-calc-history-close');
                const drawer = document.getElementById(id);
                const backdrop = document.getElementById(id + '-backdrop');
                if (drawer) drawer.hidden = true;
                if (backdrop) backdrop.hidden = true;
            }
        });
    </script>
@endonce
