@extends('admin.layout')

@section('content')
    <h1>仕様書セッション一覧</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>アカウント</th>
                <th>登録メールアドレス</th>
                <th>担当者</th>
                <th>テンプレート版ID</th>
                <th>ステータス</th>
                <th>メモ</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($sessions as $s)
                <tr>
                    <td>{{ $s->id }}</td>
                    <td>
                        <div>{{ $s->account_display_name ?? '' }}</div>
                        <div class="muted">ID: {{ $s->account_id }}</div>
                    </td>
                    <td>{{ $s->customer_emails ?? '-' }}</td>
                    <td>{{ $s->assignee_name ?? '-' }}</td>
                    <td>{{ $s->template_version_id }}</td>
                    <td>{{ $s->status }}</td>
                    <td>{{ $s->memo ?? '-' }}</td>
                    <td>{{ $s->updated_at }}</td>
                    <td><a href="{{ route('ops.sessions.show', $s->id) }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
