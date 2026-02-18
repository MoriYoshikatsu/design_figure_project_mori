@extends('work.layout')

@section('content')
    <h1>テンプレート版編集</h1>
    <div class="muted">テンプレート: {{ $template->template_code }}</div>

    <form method="POST" action="{{ route('work.templates.versions.edit-request.update', [$template->id, $version->id]) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>バージョン</label>
                <input type="number" name="version" value="{{ old('version', $version->version) }}">
            </div>
            <div class="col">
                <label>ルールフォーマット</label>
                <input type="text" value="システム固定（{{ $version->dsl_version ?: '0.2' }}）" readonly>
            </div>
            <div class="col">
                <label>有効</label>
                <div>
                    <input type="checkbox" name="active" value="1" @if(old('active', $version->active ? '1' : '0') === '1') checked @endif> 有効
                </div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>ルール定義(JSON)</label>
            <textarea name="dsl_json">{{ old('dsl_json', $dslJson) }}</textarea>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo', $version->memo) }}</textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">更新</button>
            <a href="{{ route('work.templates.edit', $template->id) }}">戻る</a>
        </div>
    </form>
    <form method="POST" action="{{ route('work.templates.versions.edit-request.delete', [$template->id, $version->id]) }}" style="display:inline;">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <button type="submit" onclick="return confirm('このテンプレート版の削除申請を送信しますか？')">削除申請</button>
    </form>
@endsection
