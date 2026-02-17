@extends('work.layout')

@section('content')
    <h1>価格表編集</h1>
    <form method="POST" action="{{ route('work.price-books.edit-request.update', $book->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>名称</label>
                <input type="text" name="name" value="{{ old('name', $book->name) }}">
            </div>
            <div class="col">
                <label>バージョン</label>
                <input type="number" name="version" value="{{ old('version', $book->version) }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>通貨</label>
                <input type="text" name="currency" value="{{ old('currency', $book->currency) }}">
            </div>
            <div class="col">
                <label>有効開始日</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', $book->valid_from) }}">
            </div>
            <div class="col">
                <label>有効終了日</label>
                <input type="date" name="valid_to" value="{{ old('valid_to', $book->valid_to) }}">
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo', $book->memo) }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">更新</button>
        </div>
    </form>
    <form method="POST" action="{{ route('work.price-books.edit-request.delete', $book->id) }}" style="display:inline;">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <button type="submit" onclick="return confirm('この価格表の削除申請を送信しますか？')">削除申請</button>
    </form>

    <hr style="margin:16px 0;">

    <h2>明細</h2>
    <form method="GET" action="{{ route('work.price-books.edit', $book->id) }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="item_q" value="{{ $itemFilters['item_q'] ?? '' }}" placeholder="明細ID / SKU / モデル / メモ / 式">
            </div>
            <div class="col">
                <label>価格モデル</label>
                <select name="pricing_model">
                    <option value="">すべて</option>
                    @foreach($pricingModelOptions as $opt)
                        <option value="{{ $opt }}" @if(($itemFilters['pricing_model'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>SKU</label>
                <select name="sku_id">
                    <option value="">すべて</option>
                    @foreach($skus as $sku)
                        <option value="{{ $sku->id }}" @if(($itemFilters['sku_id'] ?? '') == (string)$sku->id) selected @endif>{{ $sku->sku_code }} / {{ $sku->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>メモ</label>
                <select name="item_has_memo">
                    <option value="">すべて</option>
                    @foreach($presenceOptions as $key => $label)
                        <option value="{{ $key }}" @if(($itemFilters['item_has_memo'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>最小数量（最小）</label>
                <input type="number" step="0.001" name="min_qty_min" value="{{ $itemFilters['min_qty_min'] ?? '' }}">
            </div>
            <div class="col">
                <label>最小数量（最大）</label>
                <input type="number" step="0.001" name="min_qty_max" value="{{ $itemFilters['min_qty_max'] ?? '' }}">
            </div>
            <div class="col">
                <label>単価（最小）</label>
                <input type="number" step="0.01" name="unit_price_min" value="{{ $itemFilters['unit_price_min'] ?? '' }}">
            </div>
            <div class="col">
                <label>単価（最大）</label>
                <input type="number" step="0.01" name="unit_price_max" value="{{ $itemFilters['unit_price_max'] ?? '' }}">
            </div>
            <div class="col">
                <label>mm単価（最小）</label>
                <input type="number" step="0.0001" name="price_per_mm_min" value="{{ $itemFilters['price_per_mm_min'] ?? '' }}">
            </div>
            <div class="col">
                <label>mm単価（最大）</label>
                <input type="number" step="0.0001" name="price_per_mm_max" value="{{ $itemFilters['price_per_mm_max'] ?? '' }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>明細更新日（開始）</label>
                <input type="date" name="item_updated_from" value="{{ $itemFilters['item_updated_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>明細更新日（終了）</label>
                <input type="date" name="item_updated_to" value="{{ $itemFilters['item_updated_to'] ?? '' }}">
            </div>
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">絞り込み</button>
            <a href="{{ route('work.price-books.edit', $book->id) }}">クリア</a>
        </div>
    </form>

    <div class="muted" style="margin:8px 0;">
        表示件数: {{ count($items) }}件
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>モデル</th>
                <th>単価</th>
                <th>mm単価</th>
                <th>式</th>
                <th>最小数量</th>
                <th>メモ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $it)
                <tr>
                    <td>{{ $it->id }}</td>
                    <td>{{ $it->sku_name }}</td>
                    <td>{{ $it->pricing_model }}</td>
                    <td>{{ $it->unit_price }}</td>
                    <td>{{ $it->price_per_mm }}</td>
                    <td><span class="muted">{{ $it->formula }}</span></td>
                    <td>{{ $it->min_qty }}</td>
                    <td>{{ $it->memo ?? '-' }}</td>
                    <td class="actions">
                        @if(!empty($it->is_pending_create))
                            <span class="muted">申請中（CREATE）</span>
                        @else
                            @if(!empty($it->pending_operation))
                                <span class="muted">申請中（{{ $it->pending_operation }}）</span>
                            @endif
                            <a href="{{ route('work.price-books.items.edit', [$book->id, $it->id]) }}">編集</a>
                            <form method="POST" action="{{ route('work.price-books.items.edit-request.delete', [$book->id, $it->id]) }}">
                                @csrf
                                <input type="hidden" name="_mode" value="submit">
                                <button type="submit">削除申請</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="margin-top:16px;">明細追加</h3>
    <form method="POST" action="{{ route('work.price-books.items.edit-request.create', $book->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>SKU名</label>
                <select name="sku_id">
                    @foreach($skus as $sku)
                        <option value="{{ $sku->id }}">{{ $sku->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>価格モデル</label>
                <select name="pricing_model">
                    <option value="FIXED">FIXED</option>
                    <option value="PER_MM">PER_MM</option>
                    <option value="FORMULA">FORMULA</option>
                </select>
            </div>
            <div class="col">
                <label>最小数量</label>
                <input type="number" step="0.001" name="min_qty" value="1">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>単価</label>
                <input type="number" step="0.01" name="unit_price">
            </div>
            <div class="col">
                <label>mm単価</label>
                <input type="number" step="0.0001" name="price_per_mm">
            </div>
            <div class="col">
                <label>式（JSON）</label>
                <input type="text" name="formula" placeholder='{"type":"linear","base":500,"k":1.2}'>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo') }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">追加</button>
        </div>
    </form>
@endsection
