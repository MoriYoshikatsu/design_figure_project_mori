@extends('work.layout')

@section('content')
    <h1>価格表明細編集</h1>
    <div class="muted">価格表: {{ $book->name }} (v{{ $book->version }})</div>

    <form method="POST" action="{{ route('work.price-books.items.edit-request.update', [$book->id, $item->id]) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>SKU名</label>
                <select name="sku_id">
                    @foreach($skus as $sku)
                        <option value="{{ $sku->id }}" @if((int)$item->sku_id === (int)$sku->id) selected @endif>
                            {{ $sku->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>価格モデル</label>
                <select name="pricing_model">
                    @foreach(['FIXED','PER_MM','FORMULA'] as $m)
                        <option value="{{ $m }}" @if($item->pricing_model === $m) selected @endif>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>最小数量</label>
                <input type="number" step="0.001" name="min_qty" value="{{ old('min_qty', $item->min_qty) }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>単価</label>
                <input type="number" step="0.01" name="unit_price" value="{{ old('unit_price', $item->unit_price) }}">
            </div>
            <div class="col">
                <label>mm単価</label>
                <input type="number" step="0.0001" name="price_per_mm" value="{{ old('price_per_mm', $item->price_per_mm) }}">
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>式（JSON）</label>
            <textarea name="formula">{{ old('formula', $formulaJson) }}</textarea>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo', $item->memo) }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">更新</button>
            <a href="{{ route('work.price-books.edit', $book->id) }}">戻る</a>
        </div>
    </form>
    <form method="POST" action="{{ route('work.price-books.items.edit-request.delete', [$book->id, $item->id]) }}" style="display:inline;">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <button type="submit" onclick="return confirm('この明細の削除申請を送信しますか？')">削除申請</button>
    </form>
@endsection
