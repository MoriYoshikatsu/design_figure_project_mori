@extends('work.layout')

@section('content')
    <h1>テンプレ編集</h1>
    <form method="POST" action="{{ route('work.templates.edit-request.update', $template->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>テンプレートコード</label>
                <input type="text" name="template_code" value="{{ old('template_code', $template->template_code) }}">
            </div>
            <div class="col">
                <label>名称</label>
                <input type="text" name="name" value="{{ old('name', $template->name) }}">
            </div>
            <div class="col">
                <label>有効</label>
                <div>
                    <input type="checkbox" name="active" value="1" @if(old('active', $template->active ? '1' : '0') === '1') checked @endif> 有効
                </div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo', $template->memo) }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">更新</button>
        </div>
    </form>
    <form method="POST" action="{{ route('work.templates.edit-request.delete', $template->id) }}" style="display:inline;">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <button type="submit" onclick="return confirm('このテンプレートの削除申請を送信しますか？')">削除申請</button>
    </form>

    <hr style="margin:16px 0;">

    <h2>バージョン一覧</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>バージョン</th>
                <th>DSLバージョン</th>
                <th>有効</th>
                <th>メモ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($versions as $v)
                <tr>
                    <td>{{ $v->id }}</td>
                    <td>{{ $v->version }}</td>
                    <td>{{ $v->dsl_version }}</td>
                    <td>{{ $v->active ? '有効' : '無効' }}</td>
                    <td>{{ $v->memo ?? '-' }}</td>
                    <td class="actions">
                        @if(!empty($v->is_pending_create))
                            <span class="muted">申請中（CREATE）</span>
                        @else
                            @if(!empty($v->pending_operation))
                                <span class="muted">申請中（{{ $v->pending_operation }}）</span>
                            @endif
                            <a href="{{ route('work.templates.versions.edit', [$template->id, $v->id]) }}">編集</a>
                            <form method="POST" action="{{ route('work.templates.versions.edit-request.delete', [$template->id, $v->id]) }}">
                                @csrf
                                <input type="hidden" name="_mode" value="submit">
                                <button type="submit" onclick="return confirm('このバージョンの削除申請を送信しますか？')">削除申請</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="margin-top:16px;">バージョン追加</h3>
    <form method="POST" action="{{ route('work.templates.versions.edit-request.create', $template->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>バージョン</label>
                <input type="number" name="version" value="{{ old('version', $nextVersion) }}">
            </div>
            <div class="col">
                <label>DSLバージョン</label>
                <input type="text" name="dsl_version" value="{{ old('dsl_version', '0.2') }}">
            </div>
            <div class="col">
                <label>有効</label>
                <div>
                    <input type="checkbox" name="active" value="1" @if(old('active', '1') === '1') checked @endif> 有効
                </div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>dsl_json</label>
            <textarea name="dsl_json">{{ old('dsl_json') }}</textarea>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo') }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">追加</button>
        </div>
    </form>
@endsection
