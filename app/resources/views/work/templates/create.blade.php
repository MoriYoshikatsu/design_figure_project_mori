@extends('work.layout')

@section('content')
    <h1>テンプレ作成</h1>
    <form method="POST" action="{{ route('work.templates.edit-request.create') }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>テンプレートコード</label>
                <input type="text" name="template_code" value="{{ old('template_code') }}">
            </div>
            <div class="col">
                <label>名称</label>
                <input type="text" name="name" value="{{ old('name') }}">
            </div>
            <div class="col">
                <label>有効</label>
                <div>
                    <input type="checkbox" name="active" value="1" @if(old('active', '1') === '1') checked @endif> 有効
                </div>
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
