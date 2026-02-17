@extends('work.layout')

@section('content')
    <h1>監査ログ #{{ $log->id }}</h1>

    <div class="actions" style="margin-bottom:10px;">
        <a href="{{ route('work.audit-logs.index') }}">一覧へ戻る</a>
    </div>

    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $log->id }}</td></tr>
            <tr><th>実行者名</th><td>{{ $log->actor_name ?? '-' }}</td></tr>
            <tr><th>アクション</th><td>{{ $log->action }}</td></tr>
            <tr><th>対象種別</th><td>{{ $log->entity_type }}</td></tr>
            <tr><th>対象ID</th><td>{{ $log->entity_id }}</td></tr>
            <tr><th>作成日</th><td>{{ $log->created_at }}</td></tr>
        </tbody>
    </table>

    <h3 style="margin-top:16px;">変更差分</h3>
    <table>
        <thead>
            <tr>
                <th>パス</th>
                <th>変更前</th>
                <th>変更後</th>
                <th>状態</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><code>{{ $row['path'] }}</code></td>
                    <td style="white-space:pre-wrap; word-break:break-all;">{{ is_array($row['before']) ? json_encode($row['before'], JSON_UNESCAPED_UNICODE) : (is_object($row['before']) ? json_encode($row['before'], JSON_UNESCAPED_UNICODE) : ($row['before'] ?? '-')) }}</td>
                    <td style="white-space:pre-wrap; word-break:break-all;">{{ is_array($row['after']) ? json_encode($row['after'], JSON_UNESCAPED_UNICODE) : (is_object($row['after']) ? json_encode($row['after'], JSON_UNESCAPED_UNICODE) : ($row['after'] ?? '-')) }}</td>
                    <td>{{ $row['changed'] ? '変更あり' : '同一' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">差分はありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
