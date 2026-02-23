@php
    $indexRouteName = (string)($indexRouteName ?? 'work.price-books.index');
    $filters = is_array($filters ?? null) ? $filters : [];
    $currencyOptions = is_array($currencyOptions ?? null) ? $currencyOptions : [];
    $periodOptions = is_array($periodOptions ?? null) ? $periodOptions : [];
    $skuFilters = is_array($skuFilters ?? null) ? $skuFilters : [];
@endphp

<div class="actions" style="margin:8px 0;">
    <a href="{{ route('work.price-books.create') }}">価格表作成</a>
</div>

<form method="GET" action="{{ route($indexRouteName) }}" style="margin:12px 0;">
    <input type="hidden" name="tab" value="price_books" class="catalog-active-tab-input">
    @foreach($skuFilters as $key => $value)
        <input type="hidden" name="sku[{{ $key }}]" value="{{ $value }}">
    @endforeach

    <div class="row">
        <div class="col">
            <label>フリーワード</label>
            <input type="text" name="pb[q]" value="{{ $filters['q'] ?? '' }}" placeholder="名称/バージョン/メモ など">
        </div>
        <div class="col">
            <label>通貨</label>
            <select name="pb[currency]">
                <option value="">すべて</option>
                @foreach($currencyOptions as $opt)
                    <option value="{{ $opt }}" @if(($filters['currency'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="col">
            <label>有効期間状態</label>
            <select name="pb[period]">
                <option value="">すべて</option>
                @foreach($periodOptions as $key => $label)
                    <option value="{{ $key }}" @if(($filters['period'] ?? '') === $key) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col">
            <label>作成日（開始）</label>
            <input type="date" name="pb[created_from]" value="{{ $filters['created_from'] ?? '' }}">
        </div>
        <div class="col">
            <label>作成日（終了）</label>
            <input type="date" name="pb[created_to]" value="{{ $filters['created_to'] ?? '' }}">
        </div>
        <div class="col">
            <label>更新日（開始）</label>
            <input type="date" name="pb[updated_from]" value="{{ $filters['updated_from'] ?? '' }}">
        </div>
        <div class="col">
            <label>更新日（終了）</label>
            <input type="date" name="pb[updated_to]" value="{{ $filters['updated_to'] ?? '' }}">
        </div>
    </div>

    <div class="actions" style="margin-top:8px;">
        <button type="submit">絞り込み</button>
        <a href="{{ route($indexRouteName, ['tab' => 'price_books', 'sku' => $skuFilters]) }}">クリア</a>
    </div>
</form>

<div class="muted" style="margin:8px 0;">{{ count($books) }}件</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>名称</th>
            <th>バージョン</th>
            <th>通貨</th>
            <th>有効期間</th>
            <th>メモ</th>
            <th>更新日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($books as $b)
            @php
                $isPendingCreate = !empty($b->is_pending_create);
                $pendingOperation = (string)($b->pending_operation ?? ($isPendingCreate ? 'CREATE' : ''));
                $statusText = $isPendingCreate ? '申請中（CREATE）' : ($pendingOperation !== '' ? '申請中（' . $pendingOperation . '）' : '通常');
                $statusTone = 'normal';
                if ($pendingOperation === 'DELETE') {
                    $statusTone = 'danger';
                } elseif ($pendingOperation !== '' || $isPendingCreate) {
                    $statusTone = 'pending';
                }

                $links = [];
                $delete = null;
                if ($isPendingCreate && !empty($b->pending_request_id)) {
                    $links[] = [
                        'label' => '申請詳細',
                        'url' => route('work.change-requests.show', (int)$b->pending_request_id),
                    ];
                } elseif (is_numeric((string)$b->id)) {
                    $links[] = [
                        'label' => '詳細',
                        'url' => route('work.price-books.show', (int)$b->id),
                    ];
                    $links[] = [
                        'label' => '編集',
                        'url' => route('work.price-books.edit', (int)$b->id),
                    ];
                    $delete = [
                        'url' => route('work.price-books.edit-request.delete', (int)$b->id),
                        'label' => '削除申請',
                        'confirm' => 'この価格表の削除申請を送信しますか？',
                    ];
                }

                $payload = [
                    'kind' => 'price_book',
                    'title' => '価格表詳細',
                    'subtitle' => (string)$b->id,
                    'status_text' => $statusText,
                    'status_tone' => $statusTone,
                    'details' => [
                        ['label' => 'ID', 'value' => (string)$b->id],
                        ['label' => '名称', 'value' => (string)($b->name ?? '')],
                        ['label' => 'バージョン', 'value' => (string)($b->version ?? '-')],
                        ['label' => '通貨', 'value' => (string)($b->currency ?? '-')],
                        ['label' => '有効期間', 'value' => trim((string)($b->valid_from ?? '')) . ' ~ ' . trim((string)($b->valid_to ?? ''))],
                        ['label' => 'メモ', 'value' => (string)($b->memo ?? '-')],
                        ['label' => '更新日', 'value' => (string)($b->updated_at ?? '-')],
                    ],
                    'links' => $links,
                    'delete' => $delete,
                ];
            @endphp
            <tr>
                <td>{{ $b->id }}</td>
                <td>{{ $b->name }}</td>
                <td>{{ $b->version }}</td>
                <td>{{ $b->currency }}</td>
                <td>{{ $b->valid_from }} ~ {{ $b->valid_to }}</td>
                <td>{{ $b->memo ?? '-' }}</td>
                <td>{{ $b->updated_at ?? '-' }}</td>
                <td class="actions">
                    @if(!empty($b->pending_operation) || !empty($b->is_pending_create))
                        <span class="muted">{{ $statusText }}</span>
                    @endif
                    <button
                        type="button"
                        class="catalog-open-drawer"
                        data-catalog-item='@json($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)'
                    >
                        詳細
                    </button>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
