@extends('admin.layout')

@section('content')
    <h1>編集承認リクエスト</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>対象種別</th>
                <th>対象ID</th>
                <th>ステータス</th>
                <th>アカウント表示名</th>
                <th>登録メールアドレス</th>
                <th>担当者</th>
                <th>メモ</th>
                <th>作成日</th>
                <th>申請者</th>
                <th>承認者</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($requests as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->entity_type }}</td>
                    <td>{{ $r->entity_id }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ $r->requested_by_account_display_name ?? '-' }}</td>
                    <td>{{ $r->requested_by_email ?? '-' }}</td>
                    <td>{{ $r->requested_by_assignee_name ?? '-' }}</td>
                    <td>{{ $r->memo ?? '-' }}</td>
                    <td>{{ $r->created_at }}</td>
                    <td>{{ $r->requested_by_account_display_name ?? ('ID: '.$r->requested_by) }}</td>
                    <td>{{ $r->approved_by_account_display_name ?? ($r->approved_by ? 'ID: '.$r->approved_by : '-') }}</td>
                    <td><a href="{{ route('admin.change-requests.show', $r->id) }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
