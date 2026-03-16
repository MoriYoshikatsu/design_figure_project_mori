@php
    $indexRouteName = (string)($indexRouteName ?? 'work.skus.index');
    $filters = is_array($filters ?? null) ? $filters : [];
    $categories = is_array($categories ?? null) ? $categories : [];
    $presenceOptions = is_array($presenceOptions ?? null) ? $presenceOptions : [];
    $priceBookFilters = is_array($priceBookFilters ?? null) ? $priceBookFilters : [];
@endphp

<div class="actions" style="margin:8px 0;">
    <a href="{{ route('work.skus.create') }}">SKU作成</a>
</div>

<form method="GET" action="{{ route($indexRouteName) }}" style="margin:12px 0;">
    <input type="hidden" name="tab" value="skus" class="catalog-active-tab-input">
    @foreach($priceBookFilters as $key => $value)
        <input type="hidden" name="pb[{{ $key }}]" value="{{ $value }}">
    @endforeach

    <div class="row">
        <div class="col">
            <label>フリーワード</label>
            <input type="text" name="sku[q]" value="{{ $filters['q'] ?? '' }}" placeholder="ID / SKU / 名称 / メモ">
        </div>
        <div class="col">
            <label>カテゴリ</label>
            <select name="sku[category]">
                <option value="">すべて</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @if(($filters['category'] ?? '') === $cat) selected @endif>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div class="col">
            <label>有効</label>
            <select name="sku[active]">
                <option value="">すべて</option>
                <option value="1" @if(($filters['active'] ?? '') === '1') selected @endif>有効</option>
                <option value="0" @if(($filters['active'] ?? '') === '0') selected @endif>無効</option>
            </select>
        </div>
        <div class="col range-field">
            <label>作成日（始点 / 終点）</label>
            <div class="range-inputs">
                <input type="date" name="sku[created_from]" value="{{ $filters['created_from'] ?? '' }}" aria-label="作成日 始点">
                <span class="range-sep">〜</span>
                <input type="date" name="sku[created_to]" value="{{ $filters['created_to'] ?? '' }}" aria-label="作成日 終点">
            </div>
        </div>
        <div class="col range-field">
            <label>更新日（始点 / 終点）</label>
            <div class="range-inputs">
                <input type="date" name="sku[updated_from]" value="{{ $filters['updated_from'] ?? '' }}" aria-label="更新日 始点">
                <span class="range-sep">〜</span>
                <input type="date" name="sku[updated_to]" value="{{ $filters['updated_to'] ?? '' }}" aria-label="更新日 終点">
            </div>
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">絞り込み</button>
            <a href="{{ route($indexRouteName, ['tab' => 'skus', 'pb' => $priceBookFilters]) }}">クリア</a>
            <div class="muted" style="margin:8px 0;">{{ count($skus) }}件</div>
        </div>
    </div>
</form>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>SKU</th>
            <th>名称</th>
            <th>カテゴリ</th>
            <th>有効</th>
            <th>メモ</th>
            <th>更新日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($skus as $s)
            @php
                $isPendingCreate = !empty($s->is_pending_create);
                $pendingOperation = (string)($s->pending_operation ?? ($isPendingCreate ? 'CREATE' : ''));
                $statusText = $isPendingCreate ? '申請中（CREATE）' : ($pendingOperation !== '' ? '申請中（' . $pendingOperation . '）' : '通常');
                $statusTone = 'normal';
                if ($pendingOperation === 'DELETE') {
                    $statusTone = 'danger';
                } elseif ($pendingOperation !== '' || $isPendingCreate) {
                    $statusTone = 'pending';
                }

                $links = [];
                $delete = null;
                if ($isPendingCreate && !empty($s->pending_request_id)) {
                    $links[] = [
                        'label' => '申請詳細',
                        'url' => route('work.change-requests.show', (int)$s->pending_request_id),
                    ];
                } elseif (is_numeric((string)$s->id)) {
                    $links[] = [
                        'label' => '編集',
                        'url' => route('work.skus.edit', (int)$s->id),
                    ];
                    $delete = [
                        'url' => route('work.skus.edit-request.delete', (int)$s->id),
                        'label' => '削除申請',
                        'confirm' => 'このSKUの削除申請を送信しますか？',
                    ];
                }

                $payload = [
                    'kind' => 'sku',
                    'title' => 'SKU詳細',
                    'subtitle' => (string)$s->id,
                    'status_text' => $statusText,
                    'status_tone' => $statusTone,
                    'details' => [
                        ['label' => 'ID', 'value' => (string)$s->id],
                        ['label' => 'SKU', 'value' => (string)($s->sku_code ?? '')],
                        ['label' => '名称', 'value' => (string)($s->name ?? '')],
                        ['label' => 'カテゴリ', 'value' => (string)($s->category ?? '')],
                        ['label' => '有効', 'value' => !empty($s->active) ? '有効' : '無効'],
                        ['label' => 'メモ', 'value' => (string)($s->memo ?? '-')],
                        ['label' => '更新日', 'value' => (string)($s->updated_at ?? '-')],
                    ],
                    'links' => $links,
                    'delete' => $delete,
                ];
            @endphp
            <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->sku_code }}</td>
                <td>{{ $s->name }}</td>
                <td>{{ $s->category }}</td>
                <td>{{ $s->active ? '有効' : '無効' }}</td>
                <td>{{ $s->memo ?? '-' }}</td>
                <td>{{ $s->updated_at }}</td>
                <td class="actions">
                    @if(!empty($s->pending_operation) || !empty($s->is_pending_create))
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
