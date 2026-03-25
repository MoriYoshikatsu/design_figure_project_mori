@extends('work.layout')

@section('content')
    <h1>パーツ</h1>
    <div class="actions" style="margin:8px 0;">
        <a href="{{ route('work.parts.create') }}">Create Part</a>
    </div>

    <form method="GET" action="{{ route('work.parts.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ID / Part / 名称 / メモ">
            </div>
            <div class="col">
                <label>カテゴリ</label>
                <select name="category">
                    <option value="">すべて</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @if(($filters['category'] ?? '') === $cat) selected @endif>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>有効</label>
                <select name="active">
                    <option value="">すべて</option>
                    <option value="1" @if(($filters['active'] ?? '') === '1') selected @endif>有効</option>
                    <option value="0" @if(($filters['active'] ?? '') === '0') selected @endif>無効</option>
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
            <a href="{{ route('work.parts.index') }}">クリア</a>
            <div class="muted" style="margin:8px 0;">{{ count($skus) }}件</div>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Part</th>
                <th>名称</th>
                <th>カテゴリ</th>
                <th>有効</th>
                <th>メモ</th>
                <th>更新日</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($skus as $s)
                <tr>
                    <td>{{ $s->id }}</td>
                    <td>{{ $s->part_code }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->category }}</td>
                    <td>{{ $s->active ? '有効' : '無効' }}</td>
                    <td>{{ $s->memo ?? '-' }}</td>
                    <td>{{ $s->updated_at }}</td>
                    <td class="actions">
                        @if(!empty($s->is_pending_create))
                            <span class="muted">申請中（CREATE）</span>
                        @else
                            @if(!empty($s->pending_operation))
                                <span class="muted">申請中（{{ $s->pending_operation }}）</span>
                            @endif
                            <a href="{{ route('work.parts.edit', $s->id) }}">編集</a>
                            <form method="POST" action="{{ route('work.parts.edit-request.delete', $s->id) }}">
                                @csrf
                                <input type="hidden" name="_mode" value="submit">
                                <button type="submit" onclick="return confirm('このPartの削除申請を送信しますか？')">削除申請</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
