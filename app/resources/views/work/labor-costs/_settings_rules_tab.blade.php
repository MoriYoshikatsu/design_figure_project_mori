@php
    $setting = $setting ?? null;
    $rules = is_array($rules ?? null) ? $rules : [];
    $processOptions = is_array($processOptions ?? null) ? $processOptions : [];
@endphp

<div class="labor-card labor-card--settings">
    <div class="labor-card-head">
        <span class="labor-kind labor-kind--settings">全体変数</span>
        <strong>時間チャージ設定</strong>
        <span class="labor-compact-note">見積共通で利用</span>
    </div>

    <form method="POST" action="{{ route('work.labor-costs.settings.edit-request.update') }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="labor-inline-wrap">
            <div class="labor-inline-form labor-inline-form--setting">
                <input type="number" min="0" step="0.01" name="hourly_rate" value="{{ old('hourly_rate', (float)($setting->hourly_rate ?? 9000)) }}" placeholder="時間チャージ（円/時）" aria-label="時間チャージ（円/時）">
                <input type="text" class="labor-text-input" name="memo" value="{{ old('memo', (string)($setting->memo ?? '')) }}" placeholder="メモ" aria-label="メモ">
                <button type="submit">更新申請</button>
            </div>
        </div>
    </form>
</div>

<details class="labor-toggle">
    <summary>自動選択ルールを追加</summary>
    <div class="labor-toggle-body">
        <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.create') }}">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <div class="labor-inline-wrap">
                <div class="labor-inline-form labor-inline-form--rule">
                    <input type="text" name="rule_code" placeholder="ルールコード" aria-label="ルールコード">
                    <input type="text" name="name" placeholder="ルール名" aria-label="ルール名">
                    <select name="process_id" aria-label="対象工程">
                        @foreach($processOptions as $processOpt)
                            <option value="{{ $processOpt['id'] }}">{{ $processOpt['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="priority" value="100" placeholder="優先度" aria-label="優先度">
                    <input type="text" name="include_tags" placeholder="include tags" aria-label="include tags">
                    <input type="text" name="exclude_tags" placeholder="exclude tags" aria-label="exclude tags">
                    <input type="text" name="required_sku_categories" placeholder="required categories" aria-label="required categories">
                    <input type="text" name="required_sku_codes" placeholder="required sku codes" aria-label="required sku codes">
                    <label class="labor-checkbox-inline" title="常時適用">
                        <input type="checkbox" name="always_apply" value="1">
                        常時
                    </label>
                    <label class="labor-checkbox-inline" title="有効">
                        <input type="checkbox" name="active" value="1" checked>
                        有効
                    </label>
                    <input type="text" class="labor-text-input" name="memo" placeholder="メモ" aria-label="メモ">
                    <button type="submit">作成申請</button>
                </div>
            </div>
        </form>
    </div>
</details>

@foreach($rules as $rule)
    @php
        $ruleId = (int)($rule['id'] ?? 0);
        $includeTags = implode(',', $rule['include_tags'] ?? []);
        $excludeTags = implode(',', $rule['exclude_tags'] ?? []);
        $requiredCategories = implode(',', $rule['required_sku_categories'] ?? []);
        $requiredCodes = implode(',', $rule['required_sku_codes'] ?? []);
    @endphp

    <div class="labor-card labor-card--rule">
        <div class="labor-card-head">
            <span class="labor-kind labor-kind--rule">ルール</span>
            <strong>{{ $rule['name'] ?? '' }}</strong>
            <span class="labor-mono">{{ $rule['rule_code'] ?? '' }}</span>
            <span class="labor-compact-note">対象工程 {{ $rule['process_name'] ?? '' }} ({{ $rule['process_code'] ?? '' }})</span>
        </div>

        <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.update', $ruleId) }}">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <div class="labor-inline-wrap">
                <div class="labor-inline-form labor-inline-form--rule">
                    <input type="text" name="rule_code" value="{{ $rule['rule_code'] ?? '' }}" placeholder="ルールコード" aria-label="ルールコード">
                    <input type="text" name="name" value="{{ $rule['name'] ?? '' }}" placeholder="ルール名" aria-label="ルール名">
                    <select name="process_id" aria-label="対象工程">
                        @foreach($processOptions as $processOpt)
                            <option value="{{ $processOpt['id'] }}" @if((int)$processOpt['id'] === (int)($rule['process_id'] ?? 0)) selected @endif>{{ $processOpt['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="priority" value="{{ $rule['priority'] ?? 100 }}" placeholder="優先度" aria-label="優先度">
                    <input type="text" name="include_tags" value="{{ $includeTags }}" placeholder="include tags" aria-label="include tags">
                    <input type="text" name="exclude_tags" value="{{ $excludeTags }}" placeholder="exclude tags" aria-label="exclude tags">
                    <input type="text" name="required_sku_categories" value="{{ $requiredCategories }}" placeholder="required categories" aria-label="required categories">
                    <input type="text" name="required_sku_codes" value="{{ $requiredCodes }}" placeholder="required sku codes" aria-label="required sku codes">
                    <label class="labor-checkbox-inline" title="常時適用">
                        <input type="checkbox" name="always_apply" value="1" @if(!empty($rule['always_apply'])) checked @endif>
                        常時
                    </label>
                    <label class="labor-checkbox-inline" title="有効">
                        <input type="checkbox" name="active" value="1" @if(!empty($rule['active'])) checked @endif>
                        有効
                    </label>
                    <input type="text" class="labor-text-input" name="memo" value="{{ $rule['memo'] ?? '' }}" placeholder="メモ" aria-label="メモ">
                    <button type="submit">更新申請</button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.delete', $ruleId) }}" class="labor-delete-inline">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <button type="submit" onclick="return confirm('このルールの削除申請を送信しますか？')">ルール削除申請</button>
        </form>
    </div>
@endforeach
