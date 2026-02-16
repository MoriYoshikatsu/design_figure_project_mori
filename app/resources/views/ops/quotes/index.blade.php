@extends('admin.layout')

@section('content')
    <h1>仕様書見積一覧</h1>
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
                    <td><a href="{{ route('ops.quotes.show', $q->id) }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
