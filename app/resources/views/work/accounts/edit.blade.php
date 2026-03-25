@extends('work.layout')

@section('content')
    <h1>アカウント #{{ $account->id }} 編集</h1>

    <form method="POST" action="{{ route('work.accounts.edit-request.update', $account->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <table>
            <tbody>
                <tr>
                    <th>種別</th>
                    <td>
                        <select name="account_type">
                            <option value="B2B" @selected(old('account_type', $account->account_type) === 'B2B')>B2B</option>
                            <option value="B2C" @selected(old('account_type', $account->account_type) === 'B2C')>B2C</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>表示名（社内呼称/所属企業など）</th>
                    <td>
                        <input type="text" name="internal_name" value="{{ old('internal_name', $account->internal_name) }}">
                        <div class="muted">未入力の場合は一覧でアカウント登録名を表示します。</div>
                    </td>
                </tr>
                <tr>
                    <th>担当者</th>
                    <td>
                        <input type="text" name="assignee_name" value="{{ old('assignee_name', $account->assignee_name) }}" placeholder="例: 営業1課 田中">
                    </td>
                </tr>
                <tr>
                    <th>顧客別仕切係数</th>
                    <td>
                        @if(($supportsCustomerFactorDefault ?? false) === true)
                            <input
                                type="number"
                                name="customer_factor_default"
                                value="{{ old('customer_factor_default', $customerFactorDefaultValue ?? '1') }}"
                                min="0"
                                step="0.000001"
                                inputmode="decimal"
                                placeholder="例: 0.95"
                            >
                            <div class="muted">そのアカウントの /configurator と見積発行時の既定値として自動適用されます。未入力は 1 です。</div>
                        @else
                            <div class="muted">この環境ではまだ設定できません。最新の migration 適用後に利用できます。</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>メモ</th>
                    <td>
                        <textarea name="memo" rows="2" style="width:100%;">{{ old('memo', $account->memo) }}</textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="actions" style="margin-top:12px;">
            <button type="submit">保存</button>
            <a href="{{ route('work.accounts.index') }}">一覧へ戻る</a>
        </div>
    </form>
        <a href="{{ route('work.accounts.permissions', $account->id) }}">このアカウントの変更申請必須設定ページへ</a>
@endsection
