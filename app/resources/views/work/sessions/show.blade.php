@extends('work.layout')

@section('content')
    <h1>仕様書セッション #{{ $session->id ?? '' }} 詳細</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.sessions.index') }}">一覧へ戻る</a>
    </div>

    @php
        $config = json_decode($configJson, true) ?? [];
        $derived = json_decode($derivedJson, true) ?? [];
        $errors = json_decode($errorsJson, true) ?? [];
        $snapshotView = [
            'config' => $config,
            'derived' => $derived,
            'validation_errors' => $errors,
            'bom' => [],
            'pricing' => [],
            'totals' => [],
            'template_version_id' => (int)($session->template_version_id ?? 0),
            'price_book_id' => null,
        ];
    @endphp

    @include('snapshot_bundle', [
        'panelTitle' => 'スナップショット',
        'pdfUrl' => route('work.sessions.snapshot.pdf', $session->id),
        'summaryItems' => [
            ['label' => 'セッションID', 'value' => $session->id],
            ['label' => 'ステータス', 'value' => $session->status],
            ['label' => '作成アカウント', 'value' => $session->account_display_name ?? ''],
            ['label' => 'メールアドレス', 'value' => $session->account_emails ?? '-'],
            ['label' => '担当者', 'value' => $session->assignee_name ?? '-'],
            ['label' => '承認リクエスト数', 'value' => is_countable($requests) ? count($requests) : 0],
        ],
        'showMemoCard' => true,
        'memoValue' => $session->memo ?? '',
        'memoReadonly' => true,
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
