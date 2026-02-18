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
    <div class="muted" style="margin:8px 0;">表示件数: {{ count($items) }}件</div>

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
                <th></th>
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
                    <td class="actions">
                        @if(!empty($it->pending_operation))
                            <span class="muted">申請中（{{ $it->pending_operation }}）</span>
                        @else
                            <a href="{{ route('work.price-books.items.edit', [$book->id, $it->id]) }}">編集</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">明細はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
