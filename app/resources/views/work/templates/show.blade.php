@extends('work.layout')

@section('content')
    <h1>DSLテンプレ 詳細 #{{ $template->id }}</h1>

    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.templates.index') }}">一覧へ戻る</a>
        <a href="{{ route('work.templates.edit', $template->id) }}">編集画面へ</a>
    </div>

    @if(!empty($templatePendingOperation))
        <div class="muted" style="margin:8px 0;">
            このテンプレートには申請中の操作があります（{{ $templatePendingOperation }}）。
        </div>
    @endif

    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $template->id }}</td></tr>
            <tr><th>テンプレートコード</th><td>{{ $template->template_code }}</td></tr>
            <tr><th>名称</th><td>{{ $template->name }}</td></tr>
            <tr><th>有効</th><td>{{ $template->active ? '有効' : '無効' }}</td></tr>
            <tr><th>メモ</th><td>{{ $template->memo ?? '-' }}</td></tr>
            <tr><th>作成日</th><td>{{ $template->created_at ?? '-' }}</td></tr>
            <tr><th>更新日</th><td>{{ $template->updated_at ?? '-' }}</td></tr>
        </tbody>
    </table>

    <h2 style="margin-top:16px;">DSLバージョン一覧</h2>
    <div class="muted" style="margin:8px 0;">表示件数: {{ count($versions) }}件</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>バージョン</th>
                <th>DSLバージョン</th>
                <th>有効</th>
                <th>メモ</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($versions as $v)
                <tr>
                    <td>{{ $v->id }}</td>
                    <td>{{ $v->version }}</td>
                    <td>{{ $v->dsl_version ?: '-' }}</td>
                    <td>{{ $v->active ? '有効' : '無効' }}</td>
                    <td>{{ $v->memo ?? '-' }}</td>
                    <td>{{ $v->updated_at ?? '-' }}</td>
                    <td class="actions">
                        @if(!empty($v->is_pending_create))
                            <span class="muted">申請中（CREATE）</span>
                        @elseif(!empty($v->pending_operation))
                            <span class="muted">申請中（{{ $v->pending_operation }}）</span>
                        @else
                            <a href="{{ route('work.templates.versions.edit', [$template->id, $v->id]) }}">編集</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">バージョンはありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
