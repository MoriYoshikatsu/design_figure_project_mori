@extends('work.layout')

@section('content')
    <h1>SKU作成</h1>
    <form method="POST" action="{{ route('work.skus.edit-request.create') }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>SKUコード</label>
                <input type="text" name="sku_code" value="{{ old('sku_code') }}">
            </div>
            <div class="col">
                <label>名称</label>
                <input type="text" name="name" value="{{ old('name') }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>カテゴリ</label>
                <select name="category">
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @if(old('category') === $cat) selected @endif>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>有効</label>
                <div>
                    <input type="checkbox" name="active" value="1" @if(old('active', '1') === '1') checked @endif> 有効
                </div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>attributes（JSON）</label>
            <textarea name="attributes">{{ old('attributes') }}</textarea>
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
