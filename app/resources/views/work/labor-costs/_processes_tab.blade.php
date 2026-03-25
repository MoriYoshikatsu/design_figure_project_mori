@php
    $processes = is_array($processes ?? null) ? $processes : [];
@endphp

<details class="labor-toggle">
    <summary>工程を追加</summary>
    <div class="labor-toggle-body">
        <form method="POST" action="{{ route('work.labor-costs.processes.edit-request.create') }}">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <div class="labor-inline-wrap">
                <div class="labor-inline-form labor-inline-form--process">
                    <label class="labor-field">
                        <span class="labor-field-label">工程コード</span>
                        <input type="text" name="process_code" value="{{ old('process_code') }}" aria-label="工程コード">
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">工程名</span>
                        <input type="text" name="name" value="{{ old('name') }}" aria-label="工程名">
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">工程良品率</span>
                        <input type="number" step="0.000001" min="0.000001" name="default_yield_rate" value="{{ old('default_yield_rate', '0.95') }}" aria-label="工程良品率">
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">並び順</span>
                        <input type="number" name="sort_order" value="{{ old('sort_order', 100) }}" aria-label="並び順">
                    </label>
                    <label class="labor-checkbox-inline" title="有効">
                        <input type="checkbox" name="active" value="1" @if(old('active', '1') === '1') checked @endif>
                        有効
                    </label>
                    <label class="labor-field">
                        <span class="labor-field-label">メモ</span>
                        <input type="text" class="labor-text-input" name="memo" value="{{ old('memo') }}" aria-label="メモ">
                    </label>
                    <button type="submit">作成申請</button>
                </div>
            </div>
        </form>
    </div>
</details>

@foreach($processes as $process)
    @php
        $elements = is_array($process['elements'] ?? null) ? $process['elements'] : [];
        $processId = (int)($process['id'] ?? 0);
        $processCode = (string)($process['process_code'] ?? '');
    @endphp

    <details class="labor-card labor-card--process labor-card-toggle">
        <summary class="labor-card-head">
            <span class="labor-kind labor-kind--process">工程</span>
            <strong>{{ $process['name'] ?? '' }}</strong>
            <span class="labor-mono">{{ $processCode }}</span>
            <span class="labor-compact-note">要素 {{ count($elements) }} 件</span>
        </summary>

        <div class="labor-card-toggle-body">
            <form method="POST" action="{{ route('work.labor-costs.processes.edit-request.update', $processId) }}">
                @csrf
                <input type="hidden" name="_mode" value="submit">
                <div class="labor-inline-wrap">
                    <div class="labor-inline-form labor-inline-form--process">
                        <label class="labor-field">
                            <span class="labor-field-label">工程コード</span>
                            <input type="text" name="process_code" value="{{ $process['process_code'] ?? '' }}" aria-label="工程コード">
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">工程名</span>
                            <input type="text" name="name" value="{{ $process['name'] ?? '' }}" aria-label="工程名">
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">工程良品率</span>
                            <input type="number" step="0.000001" min="0.000001" name="default_yield_rate" value="{{ $process['default_yield_rate'] ?? '' }}" aria-label="工程良品率">
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">並び順</span>
                            <input type="number" name="sort_order" value="{{ $process['sort_order'] ?? 0 }}" aria-label="並び順">
                        </label>
                        <label class="labor-checkbox-inline" title="有効">
                            <input type="checkbox" name="active" value="1" @if(!empty($process['active'])) checked @endif>
                            有効
                        </label>
                        <label class="labor-field">
                            <span class="labor-field-label">メモ</span>
                            <input type="text" class="labor-text-input" name="memo" value="{{ $process['memo'] ?? '' }}" aria-label="メモ">
                        </label>
                        <button type="submit">更新申請</button>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('work.labor-costs.processes.edit-request.delete', $processId) }}" class="labor-delete-inline">
                @csrf
                <input type="hidden" name="_mode" value="submit">
                <button type="submit" onclick="return confirm('この工程の削除申請を送信しますか？')">工程削除申請</button>
            </form>

            <div class="labor-subsection">
                <div class="labor-subsection-head">
                    <span class="labor-kind labor-kind--element">工程要素</span>
                    <span class="labor-compact-note">工程ごとの作業要素を編集</span>
                </div>

                <div class="labor-row-stack">
                    @forelse($elements as $element)
                        @php
                            $elementId = (int)($element['id'] ?? 0);
                        @endphp
                        <div>
                            <form method="POST" action="{{ route('work.labor-costs.elements.edit-request.update', $elementId) }}">
                                @csrf
                                <input type="hidden" name="_mode" value="submit">
                                <input type="hidden" name="process_id" value="{{ $processId }}">
                                <div class="labor-inline-wrap">
                                    <div class="labor-inline-form labor-inline-form--element">
                                        <label class="labor-field">
                                            <span class="labor-field-label">要素コード</span>
                                            <input type="text" name="element_code" value="{{ $element['element_code'] ?? '' }}" aria-label="要素コード">
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">要素名</span>
                                            <input type="text" name="name" value="{{ $element['name'] ?? '' }}" aria-label="要素名">
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">作業時間（分）</span>
                                            <input type="number" step="0.000001" min="0" name="work_minutes" value="{{ $element['work_minutes'] ?? 0 }}" aria-label="作業時間（分）">
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">活動係数</span>
                                            <input type="number" step="0.000001" min="0" name="activity_coeff" value="{{ $element['activity_coeff'] ?? 0 }}" aria-label="活動係数">
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">1回の作業数量</span>
                                            <input type="number" step="1" min="1" name="batch_size" value="{{ $element['batch_size'] ?? 1 }}" aria-label="1回の作業数量">
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">足し算の定数項</span>
                                            <input type="number" step="0.01" min="0" name="depreciation_amount" value="{{ $element['depreciation_amount'] ?? 0 }}" aria-label="減価償却費">
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">要素良品率</span>
                                            <input type="number" step="0.000001" min="0.000001" name="default_yield_rate" value="{{ $element['default_yield_rate'] ?? '' }}" aria-label="要素良品率">
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">並び順</span>
                                            <input type="number" step="1" name="sort_order" value="{{ $element['sort_order'] ?? 0 }}" aria-label="並び順">
                                        </label>
                                        <label class="labor-checkbox-inline" title="有効">
                                            <input type="checkbox" name="active" value="1" @if(!empty($element['active'])) checked @endif>
                                            有効
                                        </label>
                                        <label class="labor-field">
                                            <span class="labor-field-label">メモ</span>
                                            <input type="text" class="labor-text-input" name="memo" value="{{ $element['memo'] ?? '' }}" aria-label="メモ">
                                        </label>
                                        <button type="submit">更新申請</button>
                                    </div>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('work.labor-costs.elements.edit-request.delete', $elementId) }}" class="labor-delete-inline">
                                @csrf
                                <input type="hidden" name="_mode" value="submit">
                                <button type="submit" onclick="return confirm('この要素の削除申請を送信しますか？')">要素削除申請</button>
                            </form>
                        </div>
                    @empty
                        <div class="labor-compact-note">要素はありません。</div>
                    @endforelse
                </div>

                <details class="labor-toggle" style="margin-top:8px; margin-bottom:0;">
                    <summary>要素を追加</summary>
                    <div class="labor-toggle-body">
                        <form method="POST" action="{{ route('work.labor-costs.elements.edit-request.create') }}">
                            @csrf
                            <input type="hidden" name="_mode" value="submit">
                            <input type="hidden" name="process_id" value="{{ $processId }}">
                            <div class="labor-inline-wrap">
                                <div class="labor-inline-form labor-inline-form--element">
                                    <label class="labor-field">
                                        <span class="labor-field-label">要素コード</span>
                                        <input type="text" name="element_code" aria-label="要素コード">
                                    </label>
                                    <label class="labor-field">
                                        <span class="labor-field-label">要素名</span>
                                        <input type="text" name="name" aria-label="要素名">
                                    </label>
                                    <label class="labor-field">
                                        <span class="labor-field-label">作業時間（分）</span>
                                        <input type="number" step="0.000001" min="0" name="work_minutes" value="0" aria-label="作業時間（分）">
                                    </label>
                                    <label class="labor-field">
                                        <span class="labor-field-label">活動係数</span>
                                        <input type="number" step="0.000001" min="0" name="activity_coeff" value="1" aria-label="活動係数">
                                    </label>
                                    <label class="labor-field">
                                        <span class="labor-field-label">1回の作業数量</span>
                                        <input type="number" min="1" step="1" name="batch_size" value="1" aria-label="1回の作業数量">
                                    </label>
                                    <label class="labor-field">
                                        <span class="labor-field-label">足し算の定数項</span>
                                        <input type="number" min="0" step="0.01" name="depreciation_amount" value="0" aria-label="減価償却費">
                                    </label>
                                    <label class="labor-field">
                                        <span class="labor-field-label">要素良品率</span>
                                        <input type="number" min="0.000001" step="0.000001" name="default_yield_rate" value="0.9" aria-label="要素良品率">
                                    </label>
                                    <label class="labor-field">
                                        <span class="labor-field-label">並び順</span>
                                        <input type="number" step="1" name="sort_order" value="100" aria-label="並び順">
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
                            </div>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </details>
@endforeach
