@extends('work.layout')

@section('content')
    <h1>アカウント 一覧</h1>

    <form method="GET" action="{{ route('work.accounts.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="名前/担当者/メモ など">
            </div>
            {{-- <div class="col">
                <label>アカウント種別</label>
                <select name="account_type">
                    <option value="">すべて</option>
                    @foreach($accountTypeOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['account_type'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div> --}}
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
                <label>作成日（開始）</label>
                <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>作成日（終了）</label>
                <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
            </div>
        {{-- </div>
        <div class="row" style="margin-top:8px;"> --}}
            <div class="col">
                <label>更新日（開始）</label>
                <input type="date" name="updated_from" value="{{ $filters['updated_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>更新日（終了）</label>
                <input type="date" name="updated_to" value="{{ $filters['updated_to'] ?? '' }}">
            </div>
        {{-- </div> --}}
            <div class="actions" style="margin-top:13px;">
                <button type="submit">絞り込み</button>
                <a href="{{ route('work.accounts.index') }}">クリア</a>
                <div class="muted" style="margin:8px 0;">{{ count($accounts) }}件</div>
            </div>
        </div>
    </form>

    @if(($canCreateAccount ?? false) === true)
        <details style="margin:12px 0;">
            <summary>アカウント新規作成（申請）</summary>
            <form method="POST" action="{{ route('work.accounts.edit-request.create') }}" style="margin-top:8px;">
                @csrf
                <div class="row">
                    <div class="col">
                        <label>権限区分</label>
                        <select name="role" required>
                            @foreach($roleOptions as $opt)
                                <option value="{{ $opt }}" @selected(old('role', 'sales') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col">
                        <label>アカウント表示名</label>
                        <input type="text" name="internal_name" value="{{ old('internal_name') }}" placeholder="例: 株式会社サンプル">
                    </div>
                    <div class="col">
                        <label>担当者名</label>
                        <input type="text" name="assignee_name" value="{{ old('assignee_name') }}" placeholder="例: 山田 太郎">
                    </div>
                </div>
                <div class="row" style="margin-top:8px;">
                    <div class="col">
                        <label>メモ</label>
                        <textarea name="memo" rows="2">{{ old('memo') }}</textarea>
                    </div>
                </div>
                <div class="actions" style="margin-top:8px;">
                    <button type="submit">作成申請を送信</button>
                </div>
            </form>
        </details>
    @endif

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
                    <td>
                        <div class="actions">
                            <a href="{{ route('work.accounts.edit', $a->id) }}">編集</a>
                            @if(($a->can_request_delete ?? false) && is_numeric((string)$a->id))
                                <form method="POST" action="{{ route('work.accounts.edit-request.delete', ['id' => $a->id]) }}" onsubmit="return confirm('アカウント #{{ $a->id }} の削除申請を送信しますか？');">
                                    @csrf
                                    <button type="submit">削除申請</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
