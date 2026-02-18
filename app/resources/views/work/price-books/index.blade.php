@extends('work.layout')

@section('content')
    <h1>パーツ価格表 一覧</h1>
    <div class="actions" style="margin:8px 0;">
        <button type=button href="{{ route('work.price-books.create') }}">価格表作成</button>
    </div>

    <form method="GET" action="{{ route('work.price-books.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="名称/バージョン/メモ など">
            </div>
            <div class="col">
                <label>通貨</label>
                <select name="currency">
                    <option value="">すべて</option>
                    @foreach($currencyOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['currency'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>有効期間状態</label>
                <select name="period">
                    <option value="">すべて</option>
                    @foreach($periodOptions as $key => $label)
                        <option value="{{ $key }}" @if(($filters['period'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        {{-- </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>バージョン（最小）</label>
                <input type="number" name="version_min" value="{{ $filters['version_min'] ?? '' }}">
            </div>
            <div class="col">
                <label>バージョン（最大）</label>
                <input type="number" name="version_max" value="{{ $filters['version_max'] ?? '' }}">
            </div>
            <div class="col">
                <label>メモ</label>
                <select name="has_memo">
                    <option value="">すべて</option>
                    @foreach($presenceOptions as $key => $label)
                        <option value="{{ $key }}" @if(($filters['has_memo'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>有効開始日（開始）</label>
                <input type="date" name="valid_from_from" value="{{ $filters['valid_from_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>有効開始日（終了）</label>
                <input type="date" name="valid_from_to" value="{{ $filters['valid_from_to'] ?? '' }}">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>有効終了日（開始）</label>
                <input type="date" name="valid_to_from" value="{{ $filters['valid_to_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>有効終了日（終了）</label>
                <input type="date" name="valid_to_to" value="{{ $filters['valid_to_to'] ?? '' }}">
            </div> --}}
            <div class="col">
                <label>作成日（開始）</label>
                <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>作成日（終了）</label>
                <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
            </div>
            <div class="col">
                <label>更新日（開始）</label>
                <input type="date" name="updated_from" value="{{ $filters['updated_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>更新日（終了）</label>
                <input type="date" name="updated_to" value="{{ $filters['updated_to'] ?? '' }}">
            </div>
            <div class="actions" style="margin-top:8px;">
                <button type="submit">絞り込み</button>
                <a href="{{ route('work.price-books.index') }}">クリア</a>
                <div class="muted" style="margin:8px 0;">{{ count($books) }}件</div>
            </div>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>バージョン</th>
                <th>通貨</th>
                <th>有効期間</th>
                <th>メモ</th>
                <th>作成日</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($books as $b)
                <tr>
                    <td>{{ $b->id }}</td>
                    <td>{{ $b->name }}</td>
                    <td>{{ $b->version }}</td>
                    <td>{{ $b->currency }}</td>
                    <td>{{ $b->valid_from }} ~ {{ $b->valid_to }}</td>
                    <td>{{ $b->memo ?? '-' }}</td>
                    <td>{{ $b->created_at ?? '-' }}</td>
                    <td>{{ $b->updated_at ?? '-' }}</td>
                    <td class="actions">
                        @if(!empty($b->is_pending_create))
                            <span class="muted">申請中（CREATE）</span>
                        @else
                            @if(!empty($b->pending_operation))
                                <span class="muted">申請中（{{ $b->pending_operation }}）</span>
                            @endif
                            <a href="{{ route('work.price-books.show', $b->id) }}">詳細</a>
                            <form method="POST" action="{{ route('work.price-books.edit-request.delete', $b->id) }}">
                                @csrf
                                <input type="hidden" name="_mode" value="submit">
                                <button type="submit" onclick="return confirm('この価格表の削除申請を送信しますか？')">削除申請</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
