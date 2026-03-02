@extends('work.layout')

@section('content')
    <h1>見積 #{{ $quote->id ?? '' }} 計算履歴</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.quotes.show', $quote->id) }}">見積詳細へ戻る</a>
    </div>

    @if(!$canExpandCalcRuns)
        <div class="muted" style="margin:8px 0;">Customer権限では重要イベントのみ表示します。</div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Run</th>
                <th>イベント</th>
                <th>Source</th>
                <th>小計Raw</th>
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
                <tr>
                    <td>{{ $run['run_no'] ?? '-' }}</td>
                    <td>{{ $run['event_label'] ?? ($run['event_type'] ?? '-') }}</td>
                    <td>
                        @php
                            $sourceType = $run['source_type'] ?? null;
                            $sourceId = $run['source_id'] ?? null;
                        @endphp
                        @if($sourceType)
                            {{ $sourceType }}@if($sourceId !== null) #{{ $sourceId }}@endif
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $run['subtotal_raw'] ?? '-' }}</td>
                    <td>{{ $run['unit_price_rounded'] ?? '-' }}</td>
                    <td>{{ $run['recomputed_total'] ?? '-' }}</td>
                    <td>{{ $run['adjusted_total'] ?? '-' }}</td>
                    <td>{{ $run['tax_rate'] ?? '-' }}</td>
                    <td>{{ $run['tax_amount'] ?? '-' }}</td>
                    <td>{{ $run['grand_total'] ?? '-' }}</td>
                    <td>{{ $run['triggered_by_name'] ?? ($run['triggered_by_email'] ?? '-') }}</td>
                    <td>{{ $run['created_at'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12">履歴はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="margin-top:14px;">各Runの計算内訳</h2>
    @forelse($runs as $run)
        @php
            $runNo = $run['run_no'] ?? '-';
            $eventLabel = $run['event_label'] ?? ($run['event_type'] ?? '-');
            $createdAt = $run['created_at'] ?? '-';
            $runInput = is_array($run['input'] ?? null) ? $run['input'] : [];
            $runSteps = is_array($run['steps'] ?? null) ? $run['steps'] : [];
            $runOutput = is_array($run['output'] ?? null) ? $run['output'] : [];
            $runContext = is_array($run['context'] ?? null) ? $run['context'] : [];
        @endphp
        <details style="margin-top:8px; border:1px solid #d1d5db; border-radius:8px; background:#fff; padding:8px;" @if($loop->first) open @endif>
            <summary>
                Run #{{ $runNo }} / {{ $eventLabel }} / {{ $createdAt }}
            </summary>

            @include('work.quotes._pricing_breakdown', [
                'sectionTitle' => '',
                'pricingInput' => $runInput,
                'pricingSteps' => $runSteps,
                'pricingOutput' => $runOutput,
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
