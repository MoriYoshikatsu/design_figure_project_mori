@php
    $setting = $setting ?? null;
    $rules = is_array($rules ?? null) ? $rules : [];
    $processOptions = is_array($processOptions ?? null) ? $processOptions : [];
@endphp

<div class="labor-card">
    <h3>全体変数</h3>
    <form method="POST" action="{{ route('work.labor-costs.settings.edit-request.update') }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>時間チャージ（円/時）</label>
                <input type="number" min="0" step="0.01" name="hourly_rate" value="{{ old('hourly_rate', (float)($setting->hourly_rate ?? 9000)) }}">
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo', (string)($setting->memo ?? '')) }}</textarea>
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">設定更新申請</button>
        </div>
    </form>
</div>

<div class="labor-card">
    <h3>自動選択ルールを追加</h3>
    <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.create') }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>ルールコード</label>
                <input type="text" name="rule_code" placeholder="RULE_MFD">
            </div>
            <div class="col">
                <label>ルール名</label>
                <input type="text" name="name" placeholder="MFDタグでMFD工程を適用">
            </div>
            <div class="col">
                <label>対象工程</label>
                <select name="process_id">
                    @foreach($processOptions as $processOpt)
                        <option value="{{ $processOpt['id'] }}">{{ $processOpt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label>優先度（小さいほど先）</label>
                <input type="number" name="priority" value="100">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>include tags（カンマ区切り）</label>
                <input type="text" name="include_tags" placeholder="connector,pm">
            </div>
            <div class="col">
                <label>exclude tags（カンマ区切り）</label>
                <input type="text" name="exclude_tags" placeholder="apc">
            </div>
            <div class="col">
                <label>required categories（カンマ区切り）</label>
                <input type="text" name="required_sku_categories" placeholder="CONNECTOR">
            </div>
            <div class="col">
                <label>required sku codes（カンマ区切り）</label>
                <input type="text" name="required_sku_codes" placeholder="PROC_MFD">
            </div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="col">
                <label>常時適用</label>
                <div><input type="checkbox" name="always_apply" value="1"> 有効</div>
            </div>
            <div class="col">
                <label>ルール有効</label>
                <div><input type="checkbox" name="active" value="1" checked> 有効</div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo"></textarea>
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">ルール作成申請</button>
        </div>
    </form>
</div>

@foreach($rules as $rule)
    @php
        $ruleId = (int)($rule['id'] ?? 0);
        $includeTags = implode(',', $rule['include_tags'] ?? []);
        $excludeTags = implode(',', $rule['exclude_tags'] ?? []);
        $requiredCategories = implode(',', $rule['required_sku_categories'] ?? []);
        $requiredCodes = implode(',', $rule['required_sku_codes'] ?? []);
    @endphp
    <div class="labor-card">
        <h3>ルール: <span class="labor-mono">{{ $rule['rule_code'] ?? '' }}</span> / {{ $rule['name'] ?? '' }}</h3>
        <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.update', $ruleId) }}">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <div class="row">
                <div class="col">
                    <label>ルールコード</label>
                    <input type="text" name="rule_code" value="{{ $rule['rule_code'] ?? '' }}">
                </div>
                <div class="col">
                    <label>ルール名</label>
                    <input type="text" name="name" value="{{ $rule['name'] ?? '' }}">
                </div>
                <div class="col">
                    <label>対象工程</label>
                    <select name="process_id">
                        @foreach($processOptions as $processOpt)
                            <option value="{{ $processOpt['id'] }}" @if((int)$processOpt['id'] === (int)($rule['process_id'] ?? 0)) selected @endif>{{ $processOpt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <label>優先度</label>
                    <input type="number" name="priority" value="{{ $rule['priority'] ?? 100 }}">
                </div>
            </div>
            <div class="row" style="margin-top:8px;">
                <div class="col">
                    <label>include tags</label>
                    <input type="text" name="include_tags" value="{{ $includeTags }}">
                </div>
                <div class="col">
                    <label>exclude tags</label>
                    <input type="text" name="exclude_tags" value="{{ $excludeTags }}">
                </div>
                <div class="col">
                    <label>required categories</label>
                    <input type="text" name="required_sku_categories" value="{{ $requiredCategories }}">
                </div>
                <div class="col">
                    <label>required sku codes</label>
                    <input type="text" name="required_sku_codes" value="{{ $requiredCodes }}">
                </div>
            </div>
            <div class="row" style="margin-top:8px;">
                <div class="col">
                    <label>常時適用</label>
                    <div><input type="checkbox" name="always_apply" value="1" @if(!empty($rule['always_apply'])) checked @endif> 有効</div>
                </div>
                <div class="col">
                    <label>ルール有効</label>
                    <div><input type="checkbox" name="active" value="1" @if(!empty($rule['active'])) checked @endif> 有効</div>
                </div>
            </div>
            <div style="margin-top:8px;">
                <label>メモ</label>
                <textarea name="memo">{{ $rule['memo'] ?? '' }}</textarea>
            </div>
            <div class="actions" style="margin-top:8px;">
                <button type="submit">ルール更新申請</button>
            </div>
        </form>
        <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.delete', $ruleId) }}" style="margin-top:6px;">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <button type="submit" onclick="return confirm('このルールの削除申請を送信しますか？')">ルール削除申請</button>
        </form>
    </div>
@endforeach

