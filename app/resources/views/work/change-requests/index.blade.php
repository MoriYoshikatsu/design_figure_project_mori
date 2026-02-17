@extends('work.layout')

@section('content')
    <h1>編集承認リクエスト</h1>

    <form method="GET" action="{{ route('work.change-requests.index') }}" style="margin:12px 0;">
        <div class="row">
            <div class="col">
                <label>フリーワード</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ID/対象種別 / ステータス / 申請者 / 承認者 / メモ">
            </div>
            <div class="col">
                <label>ステータス</label>
                <select name="status">
                    <option value="">すべて</option>
                    @foreach($statusOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['status'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>操作</label>
                <select name="operation">
                    <option value="">すべて</option>
                    @foreach($operationOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['operation'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>対象種別</label>
                <select name="entity_type">
                    <option value="">すべて</option>
                    @foreach($entityTypeOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['entity_type'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>対象ID</label>
                <input type="text" name="entity_id" value="{{ $filters['entity_id'] ?? '' }}" placeholder="数値で指定">
            </div>            
        {{-- </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>申請者ユーザーID</label>
                <select name="requested_by">
                    <option value="">すべて</option>
                    @foreach($requestedByOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['requested_by'] ?? '') == (string)$opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>承認者ユーザーID</label>
                <select name="approved_by">
                    <option value="">すべて</option>
                    @foreach($approvedByOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['approved_by'] ?? '') == (string)$opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>申請者ロール</label>
                <select name="requested_role">
                    <option value="">すべて</option>
                    @foreach($requestedRoleOptions as $opt)
                        <option value="{{ $opt }}" @if(($filters['requested_role'] ?? '') === $opt) selected @endif>{{ $opt }}</option>
                    @endforeach
                </select>
            </div> --}}
            <div class="col">
                <label>承認状態</label>
                <select name="approval_state">
                    <option value="">すべて</option>
                    @foreach($approvalStateOptions as $key => $label)
                        <option value="{{ $key }}" @if(($filters['approval_state'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        {{-- </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>コメント</label>
                <select name="has_comment">
                    <option value="">すべて</option>
                    @foreach($presenceOptions as $key => $label)
                        <option value="{{ $key }}" @if(($filters['has_comment'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>メモ</label>
                <select name="has_memo">
                    <option value="">すべて</option>
                    @foreach($presenceOptions as $key => $label)
                        <option value="{{ $key }}" @if(($filters['has_memo'] ?? '') === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div> --}}
            <div class="col">
                <label>申請日（開始）</label>
                <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>申請日（終了）</label>
                <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
            </div>
            {{-- <div class="col">
                <label>承認日（開始）</label>
                <input type="date" name="approved_from" value="{{ $filters['approved_from'] ?? '' }}">
            </div>
            <div class="col">
                <label>承認日（終了）</label>
                <input type="date" name="approved_to" value="{{ $filters['approved_to'] ?? '' }}">
            </div> --}}
            <div class="actions" style="margin-top:13px;">
                <button type="submit">絞り込み</button>
                <a href="{{ route('work.change-requests.index') }}">クリア</a>
                <div class="muted" style="margin:8px 0;">{{ count($requests) }}件</div>
            </div>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>対象種別</th>
                <th>対象ID</th>
                <th>操作</th>
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
                    <td>{{ $r->operation ?? 'UPDATE' }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ $r->requested_by_account_display_name ?? '-' }}</td>
                    <td>{{ $r->requested_by_email ?? '-' }}</td>
                    <td>{{ $r->requested_by_assignee_name ?? '-' }}</td>
                    <td>{{ $r->memo ?? '-' }}</td>
                    <td>{{ $r->created_at }}</td>
                    <td>{{ $r->requested_by_account_display_name ?? ('ID: '.$r->requested_by) }}</td>
                    <td>{{ $r->approved_by_account_display_name ?? ($r->approved_by ? 'ID: '.$r->approved_by : '-') }}</td>
                    <td><a href="{{ route('work.change-requests.show', $r->id) }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
