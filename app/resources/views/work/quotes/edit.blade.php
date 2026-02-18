@extends('work.layout')

@section('content')
    <h1>見積編集（コンフィギュレータ）</h1>
    <div class="muted">見積ID: {{ $quote->id }}</div>
    <div style="margin:8px 0;">
        <a href="{{ route('work.quotes.show', $quote->id) }}">詳細へ戻る</a>
    </div>

    @if(session('status'))
        <div style="margin:8px 0; padding:8px; border:1px solid #d1fae5; background:#ecfdf5;">
            {{ session('status') }}
        </div>
    @endif

    @livewire('configurator', [
        'quoteEditId' => (int)$quote->id,
        'quoteAccountId' => (int)($quote->account_id ?? 0),
        'initialConfig' => $initialConfig,
        'initialTemplateVersionId' => (int)$templateVersionId,
        'initialMemo' => $initialMemo ?? '',
        'initialSummaryFields' => $selectedSummaryFields ?? [],
        'summaryFieldOptions' => $summaryFieldOptions ?? [],
    ])
@endsection
