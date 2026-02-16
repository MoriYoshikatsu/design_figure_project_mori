@extends('admin.layout')

@section('content')
    <h1>アカウント一覧</h1>

    <form method="GET" action="{{ route('admin.accounts.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="アカウント表示名 / ユーザー登録名で検索">
            </div>
            <div class="col" style="flex:0 0 auto;">
                <button type="submit">検索</button>
            </div>
        </div>
    </form>

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
