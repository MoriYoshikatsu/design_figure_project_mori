@php
    $setting = $setting ?? null;
    $rules = is_array($rules ?? null) ? $rules : [];
    $processOptions = is_array($processOptions ?? null) ? $processOptions : [];
    $skuOptionsByCategory = is_array($skuOptionsByCategory ?? null) ? $skuOptionsByCategory : [];
    $createRequiredCodes = is_array(old('required_part_codes')) ? old('required_part_codes') : [];
    $createRequiredCodeSet = [];
    foreach ($createRequiredCodes as $code) {
        $normalizedCode = strtoupper(trim((string)$code));
        if ($normalizedCode !== '') {
            $createRequiredCodeSet[$normalizedCode] = true;
        }
    }
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
                <label class="labor-field">
                    <span class="labor-field-label">時間チャージ（円/時）</span>
                    <input type="number" min="0" step="0.01" name="hourly_rate" value="{{ old('hourly_rate', (float)($setting->hourly_rate ?? 9000)) }}" aria-label="時間チャージ（円/時）">
                </label>
                <label class="labor-field">
                    <span class="labor-field-label">メモ</span>
                    <input type="text" class="labor-text-input" name="memo" value="{{ old('memo', (string)($setting->memo ?? '')) }}" aria-label="メモ">
                </label>
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
                    <label class="labor-field">
                        <span class="labor-field-label">ルールコード</span>
                        <input type="text" name="rule_code" aria-label="ルールコード">
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">ルール名</span>
                        <input type="text" name="name" aria-label="ルール名">
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">対象工程</span>
                        <select name="process_id" aria-label="対象工程">
                            @foreach($processOptions as $processOpt)
                                <option value="{{ $processOpt['id'] }}">{{ $processOpt['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">並び順</span>
                        <input type="number" name="priority" value="100" aria-label="優先度">
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">補助タグ（すべて一致）</span>
                        <input type="text" name="include_tags" aria-label="補助タグ">
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">除外補助タグ</span>
                        <input type="text" name="exclude_tags" aria-label="除外補助タグ">
                    </label>
                    <label class="labor-checkbox-inline" title="常時適用">
                        <input type="checkbox" name="always_apply" value="1">
                        常時
                    </label>
                    <label class="labor-checkbox-inline" title="有効">
                        <input type="checkbox" name="active" value="1" checked>
                        有効
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">メモ</span>
                        <input type="text" class="labor-text-input" name="memo" aria-label="メモ">
                    </label>
                    <button type="submit">作成申請</button>
                </div>

                <div class="labor-field labor-rule-full">
                    <span class="labor-field-label">主条件の品目コード（複数選択した全コードがBOMに含まれるとき一致）</span>
                    <div class="labor-sku-picker">
                        @if(empty($skuOptionsByCategory))
                            <div class="labor-compact-note">選択可能なPartがありません。</div>
                        @else
                            <div class="labor-sku-picker-groups">
                                @foreach($skuOptionsByCategory as $category => $skuRows)
                                    @php
                                        $groupHasSelected = false;
                                        foreach ($skuRows as $skuRow) {
                                            $skuCode = strtoupper(trim((string)($skuRow['part_code'] ?? '')));
                                            if (isset($createRequiredCodeSet[$skuCode])) {
                                                $groupHasSelected = true;
                                                break;
                                            }
                                        }
                                    @endphp
                                    <details class="labor-sku-group" @if($groupHasSelected) open @endif>
                                        <summary>{{ $category }} ({{ count($skuRows) }})</summary>
                                        <div class="labor-sku-grid">
                                            @foreach($skuRows as $skuRow)
                                                @php
                                                    $skuCode = strtoupper(trim((string)($skuRow['part_code'] ?? '')));
                                                    $skuName = trim((string)($skuRow['name'] ?? ''));
                                                    $isChecked = isset($createRequiredCodeSet[$skuCode]);
                                                @endphp
                                                <label class="labor-sku-option">
                                                    <input type="checkbox" name="required_part_codes[]" value="{{ $skuCode }}" @if($isChecked) checked @endif>
                                                    <span>
                                                        <div class="labor-sku-option-code">{{ $skuCode }}</div>
                                                        <div class="labor-sku-option-name">{{ $skuName !== '' ? $skuName : '名称未設定' }}</div>
                                                        <div class="labor-sku-option-meta">{{ !empty($skuRow['active']) ? '有効Part' : '無効Part' }}</div>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                    </div>
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
        $requiredCodes = array_values(array_map(
            static fn ($code): string => strtoupper(trim((string)$code)),
            is_array($rule['required_part_codes'] ?? null) ? $rule['required_part_codes'] : []
        ));
        $requiredCodeSet = array_fill_keys(array_filter($requiredCodes), true);
    @endphp

    <details class="labor-card labor-card--rule labor-card-toggle">
        <summary class="labor-card-head">
            <span class="labor-kind labor-kind--rule">ルール</span>
            <strong>{{ $rule['name'] ?? '' }}</strong>
            <span class="labor-mono">{{ $rule['rule_code'] ?? '' }}</span>
            <span class="labor-compact-note">対象工程 {{ $rule['process_name'] ?? '' }} ({{ $rule['process_code'] ?? '' }})</span>
        </summary>

        <div class="labor-card-toggle-body">
            <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.update', $ruleId) }}">
                @csrf
                <input type="hidden" name="_mode" value="submit">
                <div class="labor-inline-wrap">
                    <div class="labor-inline-form labor-inline-form--rule">
                        <div class="labor-rule-help labor-rule-full">
                            主条件は品目コードです。タグは補助条件だけに使う前提です。
                        </div>

                        <label class="labor-field">
                            <span class="labor-field-label">ルールコード</span>
                            <input type="text" name="rule_code" value="{{ $rule['rule_code'] ?? '' }}" aria-label="ルールコード">
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">ルール名</span>
                            <input type="text" name="name" value="{{ $rule['name'] ?? '' }}" aria-label="ルール名">
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">対象工程</span>
                            <select name="process_id" aria-label="対象工程">
                                @foreach($processOptions as $processOpt)
                                    <option value="{{ $processOpt['id'] }}" @if((int)$processOpt['id'] === (int)($rule['process_id'] ?? 0)) selected @endif>{{ $processOpt['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">並び順</span>
                            <input type="number" name="priority" value="{{ $rule['priority'] ?? 100 }}" aria-label="優先度">
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">補助タグ（すべて一致）</span>
                            <input type="text" name="include_tags" value="{{ $includeTags }}" aria-label="補助タグ">
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">除外補助タグ</span>
                            <input type="text" name="exclude_tags" value="{{ $excludeTags }}" aria-label="除外補助タグ">
                        </label>
                        <label class="labor-checkbox-inline" title="常時適用">
                            <input type="checkbox" name="always_apply" value="1" @if(!empty($rule['always_apply'])) checked @endif>
                            常時
                        </label>
                        <label class="labor-checkbox-inline" title="有効">
                            <input type="checkbox" name="active" value="1" @if(!empty($rule['active'])) checked @endif>
                            有効
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">メモ</span>
                            <input type="text" class="labor-text-input" name="memo" value="{{ $rule['memo'] ?? '' }}" aria-label="メモ">
                        </label>
                        <button type="submit">更新申請</button>

                        <div class="labor-field labor-rule-full">
                            <span class="labor-field-label">主条件の品目コード（複数選択）</span>
                            <div class="labor-rule-help">選択した全コードがBOMに含まれるときに一致します。</div>
                            <div class="labor-sku-picker">
                                @if(empty($skuOptionsByCategory))
                                    <div class="labor-compact-note">選択可能なPartがありません。</div>
                                @else
                                    <div class="labor-sku-picker-groups">
                                        @foreach($skuOptionsByCategory as $category => $skuRows)
                                            @php
                                                $groupHasSelected = false;
                                                foreach ($skuRows as $skuRow) {
                                                    $skuCode = strtoupper(trim((string)($skuRow['part_code'] ?? '')));
                                                    if (isset($requiredCodeSet[$skuCode])) {
                                                        $groupHasSelected = true;
                                                        break;
                                                    }
                                                }
                                            @endphp
                                            <details class="labor-sku-group" @if($groupHasSelected) open @endif>
                                                <summary>{{ $category }} ({{ count($skuRows) }})</summary>
                                                <div class="labor-sku-grid">
                                                    @foreach($skuRows as $skuRow)
                                                        @php
                                                            $skuCode = strtoupper(trim((string)($skuRow['part_code'] ?? '')));
                                                            $skuName = trim((string)($skuRow['name'] ?? ''));
                                                            $isChecked = isset($requiredCodeSet[$skuCode]);
                                                        @endphp
                                                        <label class="labor-sku-option">
                                                            <input type="checkbox" name="required_part_codes[]" value="{{ $skuCode }}" @if($isChecked) checked @endif>
                                                            <span>
                                                                <div class="labor-sku-option-code">{{ $skuCode }}</div>
                                                                <div class="labor-sku-option-name">{{ $skuName !== '' ? $skuName : '名称未設定' }}</div>
                                                                <div class="labor-sku-option-meta">{{ !empty($skuRow['active']) ? '有効Part' : '無効Part' }}</div>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('work.labor-costs.rules.edit-request.delete', $ruleId) }}" class="labor-delete-inline">
                @csrf
                <input type="hidden" name="_mode" value="submit">
                <button type="submit" onclick="return confirm('このルールの削除申請を送信しますか？')">ルール削除申請</button>
            </form>
        </div>
    </details>
@endforeach
