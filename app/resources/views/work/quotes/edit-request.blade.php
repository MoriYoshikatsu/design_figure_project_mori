@extends('work.layout')

@section('content')
    <h1>見積 編集承認変更申請</h1>
    <div class="muted">ID: {{ $quote->id }}</div>

    <form method="POST" action="{{ route('work.quotes.edit-request.store', $quote->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <h3>簡易フォーム（JSONが空のときのみ反映）</h3>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>小計</label>
                <input type="number" step="0.01" name="subtotal" value="{{ old('subtotal', $totals['subtotal'] ?? '') }}">
            </div>
            <div class="col">
                <label>税</label>
                <input type="number" step="0.01" name="tax" value="{{ old('tax', $totals['tax'] ?? '') }}">
            </div>
            <div class="col">
                <label>合計</label>
                <input type="number" step="0.01" name="total" value="{{ old('total', $totals['total'] ?? '') }}">
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>スナップショット（JSON）</label>
            <div class="muted">JSONが入力されている場合はJSONが優先されます。</div>
            <textarea name="snapshot_json">{{ old('snapshot_json', $snapshotJson) }}</textarea>
        </div>
        <div style="margin-top:8px;">
            <label>コメント</label>
            <textarea name="comment">{{ old('comment') }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">承認変更申請送信</button>
            <a href="{{ route('work.quotes.show', $quote->id) }}">戻る</a>
        </div>
    </form>
@endsection
