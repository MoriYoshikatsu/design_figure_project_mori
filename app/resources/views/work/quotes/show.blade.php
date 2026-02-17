@extends('work.layout')

@section('content')
    <h1>見積 #{{ $quote->id ?? '' }} 詳細</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.quotes.edit', $quote->id) }}">コンフィギュレータで編集</a>
        <a href="{{ route('work.quotes.index') }}">一覧へ戻る</a>
    </div>

    @php
        $snapshotView = is_array($snapshot ?? null) ? $snapshot : [];
        if (!isset($snapshotView['totals']) && is_array($totals ?? null)) {
            $snapshotView['totals'] = $totals;
        }
        $config = is_array($snapshotView['config'] ?? null) ? $snapshotView['config'] : [];
        $derived = is_array($snapshotView['derived'] ?? null) ? $snapshotView['derived'] : [];
        $errors = is_array($snapshotView['validation_errors'] ?? null) ? $snapshotView['validation_errors'] : [];
    @endphp

    <h3>承認リクエスト</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ステータス</th>
                <th>作成アカウント</th>
                <th>メールアドレス</th>
                <th>担当者</th>
                <th>申請者</th>
                <th>承認者</th>
                <th>作成日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ $quote->account_display_name ?? '-' }}</td>
                    <td>{{ $quote->account_emails ?? '-' }}</td>
                    <td>{{ $r->requested_by_assignee_name ?? '-' }}</td>
                    <td>{{ $r->requested_by_account_display_name ?? ('ID: '.$r->requested_by) }}</td>
                    <td>{{ $r->approved_by_account_display_name ?? ($r->approved_by ? 'ID: '.$r->approved_by : '-') }}</td>
                    <td>{{ $r->created_at }}</td>
                    <td><a href="{{ route('work.change-requests.show', $r->id) }}">詳細</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">-</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('snapshot_bundle', [
        'panelTitle' => '見積スナップショット',
        'pdfUrl' => route('work.quotes.snapshot.pdf', $quote->id),
        'summaryItems' => is_array($summaryItems ?? null) ? $summaryItems : [],
        'includeAutoSummary' => false,
        'showMemoCard' => true,
        'memoValue' => $quote->display_memo ?? $quote->memo ?? '',
        'memoUpdateUrl' => route('work.quotes.memo.edit-request.update', $quote->id),
        'memoButtonLabel' => 'メモ保存',
        'memoReadonly' => true,
        'showCreatorColumns' => false,
        'svg' => $svg,
        'snapshot' => $snapshotView,
        'config' => $config,
        'derived' => $derived,
        'errors' => $errors,
        'snapshotJson' => $snapshotJson ?? json_encode($snapshotView, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'configJson' => $configJson ?? json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'derivedJson' => $derivedJson ?? json_encode($derived, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'errorsJson' => $errorsJson ?? json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    ])
@endsection
