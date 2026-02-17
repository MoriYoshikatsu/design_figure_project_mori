@extends('work.layout')

@section('content')
    <h1>価格表作成</h1>
    <form method="POST" action="{{ route('work.price-books.edit-request.create') }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>名称</label>
                <input type="text" name="name" value="{{ old('name', 'STANDARD') }}">
            </div>
            <div class="col">
                <label>バージョン</label>
                <input type="number" name="version" value="{{ old('version', 1) }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>通貨</label>
                <input type="text" name="currency" value="{{ old('currency', 'JPY') }}">
            </div>
            <div class="col">
                <label>有効開始日</label>
                <input type="date" name="valid_from" value="{{ old('valid_from') }}">
            </div>
            <div class="col">
                <label>有効終了日</label>
                <input type="date" name="valid_to" value="{{ old('valid_to') }}">
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo') }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">保存</button>
        </div>
    </form>
@endsection
