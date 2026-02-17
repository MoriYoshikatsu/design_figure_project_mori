@extends('admin.layout')

@section('content')
    <h1>アカウント一覧</h1>

    <form method="GET" action="{{ route('admin.accounts.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ID / アカウント名 / ユーザー名 / メール / 担当者 / メモ">
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
                <label>権限区分</label>
                <select name="role">
                    <option value="">すべて</option>
                    @foreach($roleOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['role'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>Salesルートポリシー</label>
                <select name="policy_mode">
                    <option value="">すべて</option>
                    @foreach($policyModeOptions as $key => $label)
                        <option value="{{ $key }}" @if(($filters['policy_mode'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>担当者設定</label>
                <select name="has_assignee">
                    <option value="">すべて</option>
                    @foreach($presenceOptions as $key => $label)
                        <option value="{{ $key }}" @if(($filters['has_assignee'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
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
        </div>
        <div class="row" style="margin-top:8px;">
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
            <a href="{{ route('admin.accounts.index') }}">クリア</a>
        </div>
    </form>

    <div class="muted" style="margin:8px 0;">
        表示件数: {{ count($accounts) }}件（最大200件）
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>アカウント表示名</th>
                <th>ユーザー登録名</th>
                <th>権限区分</th>
                <th>許可route</th>
                <th>担当者</th>
                <th>メモ</th>
                <th>作成日</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($accounts as $a)
                @php
                    $fallbackUserName = $a->fallback_user_name ?? null;
                    $display = $a->internal_name ?: ($fallbackUserName ?: '-');
                @endphp
                <tr>
                    <td>{{ $a->id }}</td>
                    <td>{{ $display }}</td>
                    <td>{{ $fallbackUserName ?: '-' }}</td>
                    <td>
                        <div>{{ $a->role_list ?: '未設定' }}</div>
                        {{-- <div class="muted">{{ $a->member_summary ?? '-' }}</div> --}}
                    </td>
                    <td style="white-space:pre-line;">{{ $a->route_access_summary ?? '-' }}</td>
                    <td>{{ $a->assignee_name ?? '-' }}</td>
                    <td>{{ $a->memo ?? '-' }}</td>
                    <td>{{ $a->created_at }}</td>
                    <td>{{ $a->updated_at }}</td>
                    <td><a href="{{ route('admin.accounts.edit', $a->id) }}">編集</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
