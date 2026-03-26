@extends('work.layout')

@section('content')
    <h1>見積 #{{ $quote->id }} 編集</h1>
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
        'initialSpecSheetNumber' => $initialSpecSheetNumber ?? '',
        'initialSummaryFields' => $selectedSummaryFields ?? [],
        'summaryFieldOptions' => $summaryFieldOptions ?? [],
        'initialPricingInput' => $initialPricingInput ?? [],
    ])
@endsection
