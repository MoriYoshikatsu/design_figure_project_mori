@extends('work.layout')

@section('content')
    <h1>パーツ編集</h1>
    @if($errors->any())
        <div style="margin:8px 0; padding:8px; border:1px solid #fca5a5; background:#fef2f2; color:#991b1b;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="POST" action="{{ route('work.parts.edit-request.update', $sku->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>Part code</label>
                <input type="text" name="part_code" value="{{ old('part_code', $sku->part_code) }}">
            </div>
            <div class="col">
                <label>Part Name</label>
                <input type="text" name="name" value="{{ old('name', $sku->name) }}">
                @error('name')
                    <div style="color:#dc2626; font-size:12px; margin-top:4px;">{{ $message }}</div>
                @enderror
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
        <details style="margin-top:8px;" @if($errors->has('attributes')) open @endif>
            <summary style="cursor:pointer;">attributes（JSON，編集不要）</summary>
            <div style="margin-top:8px;">
                <textarea name="attributes">{{ old('attributes', $attributesJson) }}</textarea>
            </div>
        </details>
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
