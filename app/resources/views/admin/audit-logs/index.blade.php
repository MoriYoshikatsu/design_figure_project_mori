@extends('admin.layout')

@section('content')
    <h1>監査ログ</h1>
    <form method="GET" action="{{ route('admin.audit-logs.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ID / 実行者 / アクション / 対象 / メール / 担当者">
            </div>
            <div class="col">
                <label>実行ユーザーID</label>
                <select name="actor_user_id">
                    <option value="">すべて</option>
                    @foreach($actorOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['actor_user_id'] ?? '') == (string)$opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>アクション</label>
                <select name="action">
                    <option value="">すべて</option>
                    @foreach($actionOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['action'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>対象種別</label>
                <select name="entity_type">
                    <option value="">すべて</option>
                    @foreach($entityTypeOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['entity_type'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>対象ID</label>
                <input type="text" name="entity_id" value="{{ $filters['entity_id'] ?? '' }}" placeholder="数値で指定">
            </div>
            <div class="col">
                <label>作成月</label>
                <select name="month">
                    <option value="">すべて</option>
                    @foreach($monthOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['month'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
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
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">絞り込み</button>
            <a href="{{ route('admin.audit-logs.index') }}">クリア</a>
        </div>
    </form>

    <div class="muted" style="margin:8px 0;">
        表示件数: {{ count($logs) }}件（最大300件）
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>実行ユーザーID</th>
                <th>アクション</th>
                <th>対象種別</th>
                <th>対象ID</th>
                <th>作成者アカウント表示名</th>
                <th>登録メールアドレス</th>
                <th>担当者</th>
                <th>作成日</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $l)
                <tr>
                    <td>{{ $l->id }}</td>
                    <td>{{ $l->actor_user_id }}</td>
                    <td>{{ $l->action }}</td>
                    <td>{{ $l->entity_type }}</td>
                    <td>{{ $l->entity_id }}</td>
                    <td>{{ $l->actor_account_display_name ?? '-' }}</td>
                    <td>{{ $l->actor_email ?? '-' }}</td>
                    <td>{{ $l->actor_assignee_name ?? '-' }}</td>
                    <td>{{ $l->created_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
