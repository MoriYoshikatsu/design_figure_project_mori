@extends('work.layout')

@section('content')
    <h1>納品物ルールテンプレ(DSL)管理</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.templates.create') }}">テンプレ作成</a>
    </div>

    <form method="GET" action="{{ route('work.templates.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ID / テンプレートコード / 名称 / メモ">
            </div>
            <div class="col">
                <label>有効</label>
                <select name="active">
                    <option value="">すべて</option>
                    <option value="1" @if(($filters['active'] ?? '') === '1') selected @endif>有効</option>
                    <option value="0" @if(($filters['active'] ?? '') === '0') selected @endif>無効</option>
                </select>
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
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
            <a href="{{ route('work.templates.index') }}">クリア</a>
        </div>
    </form>

    <div class="muted" style="margin:8px 0;">
        表示件数: {{ count($templates) }}件（最大200件）
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>テンプレートコード</th>
                <th>名称</th>
                <th>有効</th>
                <th>メモ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($templates as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>{{ $t->template_code }}</td>
                    <td>{{ $t->name }}</td>
                    <td>{{ $t->active ? '有効' : '無効' }}</td>
                    <td>{{ $t->memo ?? '-' }}</td>
                    <td class="actions">
                        @if(!empty($t->is_pending_create))
                            <span class="muted">申請中（CREATE）</span>
                        @else
                            @if(!empty($t->pending_operation))
                                <span class="muted">申請中（{{ $t->pending_operation }}）</span>
                            @endif
                            <a href="{{ route('work.templates.show', $t->id) }}">詳細</a>
                            <form method="POST" action="{{ route('work.templates.edit-request.delete', $t->id) }}">
                                @csrf
                                <input type="hidden" name="_mode" value="submit">
                                <button type="submit" onclick="return confirm('このテンプレートの削除申請を送信しますか？')">削除申請</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
