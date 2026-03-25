@extends('work.layout')

@section('content')
    <h1>Edit Part</h1>
    <form method="POST" action="{{ route('work.parts.edit-request.update', $sku->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>Part code</label>
                <input type="text" name="part_code" value="{{ old('part_code', $sku->part_code) }}">
            </div>
            <div class="col">
                <label>名称</label>
                <input type="text" name="name" value="{{ old('name', $sku->name) }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>英語名称</label>
                <input type="text" name="name_en" value="{{ old('name_en', $sku->name_en ?? '') }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>カテゴリ</label>
                <select name="category">
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @if(old('category', $sku->category) === $cat) selected @endif>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>有効</label>
                <div>
                    <input type="checkbox" name="active" value="1" @if(old('active', $sku->active ? '1' : '0') === '1') checked @endif> 有効
                </div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>attributes（JSON）</label>
            <textarea name="attributes">{{ old('attributes', $attributesJson) }}</textarea>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo', $sku->memo) }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">更新</button>
        </div>
    </form>
    <form method="POST" action="{{ route('work.parts.edit-request.delete', $sku->id) }}" style="display:inline;">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <button type="submit" onclick="return confirm('このPartの削除申請を送信しますか？')">削除申請</button>
    </form>
@endsection
