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

    <div style="border:1px solid #e5e7eb; background:#f9fafb; padding:12px; margin:12px 0;">
        <div style="font-weight:600; margin-bottom:8px;">概要カード表示設定</div>
        <form method="POST" action="{{ route('work.quotes.display-name-source.edit-request.update', $quote->id) }}">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <div class="row">
                <div class="col">
                    <label for="display_name_source">アカウント表示名の表示元</label>
                    <select id="display_name_source" name="display_name_source" style="width:100%;">
                        <option value="internal_name" @selected(($displayNameSource ?? 'internal_name') === 'internal_name')>accounts.internal_name を優先</option>
                        <option value="user_name" @selected(($displayNameSource ?? 'internal_name') === 'user_name')>users.name を優先</option>
                    </select>
                </div>
                <div class="col" style="flex:0 0 auto;">
                    <button type="submit">保存</button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('work.quotes.summary-fields.edit-request.update', $quote->id) }}" style="margin-top:12px;">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <div style="font-weight:600; margin-bottom:6px;">表示項目</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:6px 10px; margin-bottom:8px;">
                @foreach(($summaryFieldOptions ?? []) as $key => $label)
                    <label style="display:flex; align-items:center; gap:6px;">
                        <input
                            type="checkbox"
                            name="summary_fields[]"
                            value="{{ $key }}"
                            @checked(in_array($key, $selectedSummaryFields ?? [], true))
                        >
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <button type="submit">表示項目を保存</button>
        </form>
    </div>

    @livewire('configurator', [
        'quoteEditId' => (int)$quote->id,
        'initialConfig' => $initialConfig,
        'initialTemplateVersionId' => (int)$templateVersionId,
        'initialMemo' => $initialMemo ?? '',
    ])
@endsection
