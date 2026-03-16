@extends('work.layout')

@section('content')
    <h1>仕様書見積一覧</h1>

    <form method="GET" action="{{ route('work.quotes.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ID / アカウント / メール / 担当者 / ステータス / 合計 / メモ">
            </div>
            <div class="col">
                <label>ステータス</label>
                <select name="status">
                    <option value="">すべて</option>
                    @foreach($statusOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['status'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
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
                <label>担当者</label>
                <input type="text" name="assignee_name" value="{{ $filters['assignee_name'] ?? '' }}" placeholder="部分一致">
            </div>
            <div class="col range-field">
                <label>合計（始点 / 終点）</label>
                <div class="range-inputs">
                    <input type="number" step="0.01" name="total_min" value="{{ $filters['total_min'] ?? '' }}" placeholder="最小" aria-label="合計 始点">
                    <span class="range-sep">〜</span>
                    <input type="number" step="0.01" name="total_max" value="{{ $filters['total_max'] ?? '' }}" placeholder="最大" aria-label="合計 終点">
                </div>
            </div>
            <div class="col range-field">
                <label>作成日（始点 / 終点）</label>
                <div class="range-inputs">
                    <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}" aria-label="作成日 始点">
                    <span class="range-sep">〜</span>
                    <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}" aria-label="作成日 終点">
                </div>
            </div>
            <div class="col range-field">
                <label>更新日（始点 / 終点）</label>
                <div class="range-inputs">
                    <input type="date" name="updated_from" value="{{ $filters['updated_from'] ?? '' }}" aria-label="更新日 始点">
                    <span class="range-sep">〜</span>
                    <input type="date" name="updated_to" value="{{ $filters['updated_to'] ?? '' }}" aria-label="更新日 終点">
                </div>
            </div>
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">絞り込み</button>
            <a href="{{ route('work.quotes.index') }}">クリア</a>
            <div class="muted" style="margin:8px 0;">{{ count($quotes) }}件</div>
        </div>
    </form>


    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>アカウント</th>
                <th>登録メールアドレス</th>
                <th>担当者</th>
                <th>ステータス</th>
                <th>通貨</th>
                <th>合計</th>
                <th>メモ</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotes as $q)
                <tr>
                    <td>{{ $q->id }}</td>
                    <td>
                        <div>{{ $q->account_display_name ?? '' }}</div>
                        <div class="muted">ID: {{ $q->account_id }}</div>
                    </td>
                    <td>{{ $q->account_emails ?? '-' }}</td>
                    <td>{{ $q->assignee_name ?? '-' }}</td>
                    <td>{{ $q->status }}</td>
                    <td>{{ $q->currency }}</td>
                    <td>{{ format_amount($q->total) }}</td>
                    <td>{{ $q->memo ?? '-' }}</td>
                    <td>{{ $q->updated_at }}</td>
                    <td class="actions">
                        @if(!empty($q->pending_operation))
                            <span class="muted">申請中（{{ $q->pending_operation }}）</span>
                        @endif
                        <a href="{{ route('work.quotes.show', $q->id) }}">詳細</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
