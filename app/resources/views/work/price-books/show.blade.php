@extends('work.layout')

@section('content')
    <h1>パーツ価格表 #{{ $book->id }} 詳細</h1>

    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.price-books.index') }}">一覧へ戻る</a>
        <a href="{{ route('work.price-books.edit', $book->id) }}">編集画面へ</a>
    </div>

    @if(!empty($bookPendingOperation))
        <div class="muted" style="margin:8px 0;">
            この価格表には申請中の操作があります（{{ $bookPendingOperation }}）。
        </div>
    @endif

    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $book->id }}</td></tr>
            <tr><th>名称</th><td>{{ $book->name }}</td></tr>
            <tr><th>バージョン</th><td>{{ $book->version }}</td></tr>
            <tr><th>通貨</th><td>{{ $book->currency }}</td></tr>
            <tr><th>有効開始日</th><td>{{ $book->valid_from ?? '-' }}</td></tr>
            <tr><th>有効終了日</th><td>{{ $book->valid_to ?? '-' }}</td></tr>
            <tr><th>メモ</th><td>{{ $book->memo ?? '-' }}</td></tr>
            <tr><th>作成日</th><td>{{ $book->created_at ?? '-' }}</td></tr>
            <tr><th>更新日</th><td>{{ $book->updated_at ?? '-' }}</td></tr>
        </tbody>
    </table>

    <h2 style="margin-top:16px;">明細一覧</h2>
    <form method="GET" action="{{ route('work.price-books.show', $book->id) }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="item_q" value="{{ $itemFilters['item_q'] ?? '' }}" placeholder="名称/式/メモ など">
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
                <label>単価レンジ</label>
                <select name="unit_price_band">
                    <option value="">すべて</option>
                    @foreach($unitPriceBandOptions as $key => $label)
                        <option value="{{ $key }}" @if(($itemFilters['unit_price_band'] ?? '') === $key) selected @endif>{{ $label }}</option>
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
            {{-- <div class="col">
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
                <label>m単価（最小）</label>
                <input type="number" step="0.0001" name="price_per_m_min" value="{{ $itemFilters['price_per_m_min'] ?? '' }}">
            </div>
            <div class="col">
                <label>m単価（最大）</label>
                <input type="number" step="0.0001" name="price_per_m_max" value="{{ $itemFilters['price_per_m_max'] ?? '' }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;"> --}}
            <div class="col">
                <label>明細更新日（開始）</label>
                <input type="date" name="item_updated_from" value="{{ $itemFilters['item_updated_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>明細更新日（終了）</label>
                <input type="date" name="item_updated_to" value="{{ $itemFilters['item_updated_to'] ?? '' }}">
            </div>
            <div class="actions" style="margin-top:8px;">
                <button type="submit">絞り込み</button>
                <a href="{{ route('work.price-books.show', $book->id) }}">クリア</a>
                <div class="muted" style="margin:8px 0;">{{ count($items) }}件</div>
            </div>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>SKU</th>
                <th>モデル</th>
                <th>単価</th>
                <th>m単価</th>
                <th>式</th>
                <th>最小数量</th>
                <th>メモ</th>
                <th>更新日</th>
                {{-- <th></th> --}}
            </tr>
        </thead>
        <tbody>
            @forelse($items as $it)
                <tr>
                    <td>{{ $it->id }}</td>
                    <td>{{ $it->sku_name }}</td>
                    <td>{{ $it->sku_code }}</td>
                    <td>{{ $it->pricing_model }}</td>
                    <td>{{ $it->unit_price }}</td>
                    <td>{{ $it->price_per_m }}</td>
                    <td><span class="muted">{{ $it->formula }}</span></td>
                    <td>{{ $it->min_qty }}</td>
                    <td>{{ $it->memo ?? '-' }}</td>
                    <td>{{ $it->updated_at ?? '-' }}</td>
                    {{-- <td class="actions">
                        @if(!empty($it->pending_operation))
                            <span class="muted">申請中（{{ $it->pending_operation }}）</span>
                        @else
                            <a href="{{ route('work.price-books.items.edit', [$book->id, $it->id]) }}">編集</a>
                        @endif
                    </td> --}}
                </tr>
            @empty
                <tr>
                    <td colspan="11">明細はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
