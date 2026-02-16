@extends('admin.layout')

@section('content')
    <h1>アカウント編集 #{{ $account->id }}</h1>

    <form method="POST" action="{{ route('admin.accounts.update', $account->id) }}">
        @csrf
        @method('PUT')
        <table>
            <tbody>
                <tr>
                    <th>種別</th>
                    <td>
                        <select name="account_type">
                            <option value="B2B" @selected(old('account_type', $account->account_type) === 'B2B')>B2B</option>
                            <option value="B2C" @selected(old('account_type', $account->account_type) === 'B2C')>B2C</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>表示名（社内呼称/所属企業など）</th>
                    <td>
                        <input type="text" name="internal_name" value="{{ old('internal_name', $account->internal_name) }}">
                        <div class="muted">未入力の場合は一覧でアカウント登録名を表示します。</div>
                    </td>
                </tr>
                <tr>
                    <th>担当者</th>
                    <td>
                        <input type="text" name="assignee_name" value="{{ old('assignee_name', $account->assignee_name) }}" placeholder="例: 営業1課 田中">
                    </td>
                </tr>
                <tr>
                    <th>メモ</th>
                    <td>
                        <textarea name="memo" rows="2" style="width:100%;">{{ old('memo', $account->memo) }}</textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="actions" style="margin-top:12px;">
            <button type="submit">保存</button>
            <a href="{{ route('admin.accounts.index') }}">一覧へ戻る</a>
        </div>
    </form>

    <h2 style="margin-top:16px;">権限設定（account_user）</h2>
    <table>
        <thead>
            <tr>
                <th>user_id</th>
                <th>ユーザー名</th>
                <th>メール</th>
                <th>role</th>
                <th>メモ</th>
                <th>付与日時</th>
            </tr>
        </thead>
        <tbody>
            @forelse($members as $m)
                <tr>
                    <td>{{ $m->user_id }}</td>
                    <td>{{ $m->user_name }}</td>
                    <td>{{ $m->user_email }}</td>
                    <td>{{ $m->role }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.accounts.members.memo.update', [$account->id, $m->user_id]) }}">
                            @csrf
                            @method('PUT')
                            <textarea name="memo" rows="2" style="width:100%;">{{ old('memo', $m->memo) }}</textarea>
                            <button type="submit">保存</button>
                        </form>
                    </td>
                    <td>{{ $m->assigned_at }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">このアカウントに紐づくユーザーは未設定です。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
