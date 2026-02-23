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
@endsection
