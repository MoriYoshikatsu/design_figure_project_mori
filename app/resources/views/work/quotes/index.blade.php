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
                <label>アカウントID</label>
                <input type="text" name="account_id" value="{{ $filters['account_id'] ?? '' }}" placeholder="数値で指定">
            </div>
            <div class="col">
                <label>アカウント種別</label>
                <select name="account_type">
                    <option value="">すべて</option>
                    @foreach($accountTypeOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['account_type'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>担当者</label>
                <input type="text" name="assignee_name" value="{{ $filters['assignee_name'] ?? '' }}" placeholder="部分一致">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>合計（最小）</label>
                <input type="number" step="0.01" name="total_min" value="{{ $filters['total_min'] ?? '' }}">
            </div>
            <div class="col">
                <label>合計（最大）</label>
                <input type="number" step="0.01" name="total_max" value="{{ $filters['total_max'] ?? '' }}">
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
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">絞り込み</button>
            <a href="{{ route('work.quotes.index') }}">クリア</a>
        </div>
    </form>

    <div class="muted" style="margin:8px 0;">
        表示件数: {{ count($quotes) }}件（最大200件）
    </div>

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
                    <td>{{ $q->total }}</td>
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
