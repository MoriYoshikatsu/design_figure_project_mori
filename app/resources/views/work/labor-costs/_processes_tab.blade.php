@php
    $processes = is_array($processes ?? null) ? $processes : [];
@endphp

<div class="labor-card">
    <h3>工程を追加</h3>
    <form method="POST" action="{{ route('work.labor-costs.processes.edit-request.create') }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <div class="row">
            <div class="col">
                <label>工程コード</label>
                <input type="text" name="process_code" value="{{ old('process_code') }}" placeholder="TEC20">
            </div>
            <div class="col">
                <label>工程名</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="TEC20加工">
            </div>
            <div class="col">
                <label>工程良品率（初期値）</label>
                <input type="number" step="0.000001" min="0.000001" name="default_yield_rate" value="{{ old('default_yield_rate', '0.95') }}">
            </div>
            <div class="col">
                <label>並び順</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 100) }}">
            </div>
            <div class="col">
                <label>有効</label>
                <div><input type="checkbox" name="active" value="1" @if(old('active', '1') === '1') checked @endif> 有効</div>
            </div>
        </div>
        <div style="margin-top:8px;">
            <label>メモ</label>
            <textarea name="memo">{{ old('memo') }}</textarea>
        </div>
        <div class="actions" style="margin-top:8px;">
            <button type="submit">作成申請</button>
        </div>
    </form>
</div>

@foreach($processes as $process)
    @php
        $elements = is_array($process['elements'] ?? null) ? $process['elements'] : [];
        $processId = (int)($process['id'] ?? 0);
        $processCode = (string)($process['process_code'] ?? '');
    @endphp
    <div class="labor-card">
        <h3>工程: <span class="labor-mono">{{ $processCode }}</span> / {{ $process['name'] ?? '' }}</h3>
        <form method="POST" action="{{ route('work.labor-costs.processes.edit-request.update', $processId) }}">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <div class="row">
                <div class="col">
                    <label>工程コード</label>
                    <input type="text" name="process_code" value="{{ $process['process_code'] ?? '' }}">
                </div>
                <div class="col">
                    <label>工程名</label>
                    <input type="text" name="name" value="{{ $process['name'] ?? '' }}">
                </div>
                <div class="col">
                    <label>工程良品率（初期値）</label>
                    <input type="number" step="0.000001" min="0.000001" name="default_yield_rate" value="{{ $process['default_yield_rate'] ?? '' }}">
                </div>
                <div class="col">
                    <label>並び順</label>
                    <input type="number" name="sort_order" value="{{ $process['sort_order'] ?? 0 }}">
                </div>
                <div class="col">
                    <label>有効</label>
                    <div><input type="checkbox" name="active" value="1" @if(!empty($process['active'])) checked @endif> 有効</div>
                </div>
            </div>
            <div style="margin-top:8px;">
                <label>メモ</label>
                <textarea name="memo">{{ $process['memo'] ?? '' }}</textarea>
            </div>
            <div class="actions" style="margin-top:8px;">
                <button type="submit">更新申請</button>
            </div>
        </form>
        <form method="POST" action="{{ route('work.labor-costs.processes.edit-request.delete', $processId) }}" style="margin-top:6px;">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <button type="submit" onclick="return confirm('この工程の削除申請を送信しますか？')">削除申請</button>
        </form>

        <hr style="margin:12px 0;">
        <h4 style="margin:0 0 8px;">工程要素</h4>

        @forelse($elements as $element)
            @php
                $elementId = (int)($element['id'] ?? 0);
            @endphp
            <div style="border:1px solid #e5e7eb; border-radius:6px; padding:8px; margin-bottom:8px;">
                <form method="POST" action="{{ route('work.labor-costs.elements.edit-request.update', $elementId) }}">
                    @csrf
                    <input type="hidden" name="_mode" value="submit">
                    <input type="hidden" name="process_id" value="{{ $processId }}">
                    <div class="row">
                        <div class="col">
                            <label>要素コード</label>
                            <input type="text" name="element_code" value="{{ $element['element_code'] ?? '' }}">
                        </div>
                        <div class="col">
                            <label>要素名</label>
                            <input type="text" name="name" value="{{ $element['name'] ?? '' }}">
                        </div>
                        <div class="col">
                            <label>作業時間（分）</label>
                            <input type="number" step="0.000001" min="0" name="work_minutes" value="{{ $element['work_minutes'] ?? 0 }}">
                        </div>
                        <div class="col">
                            <label>係数</label>
                            <input type="number" step="0.000001" min="0" name="activity_coeff" value="{{ $element['activity_coeff'] ?? 0 }}">
                        </div>
                    </div>
                    <div class="row" style="margin-top:8px;">
                        <div class="col">
                            <label>1回処理数量</label>
                            <input type="number" step="1" min="1" name="batch_size" value="{{ $element['batch_size'] ?? 1 }}">
                        </div>
                        <div class="col">
                            <label>減価償却費</label>
                            <input type="number" step="0.01" min="0" name="depreciation_amount" value="{{ $element['depreciation_amount'] ?? 0 }}">
                        </div>
                        <div class="col">
                            <label>要素良品率（初期値）</label>
                            <input type="number" step="0.000001" min="0.000001" name="default_yield_rate" value="{{ $element['default_yield_rate'] ?? '' }}">
                        </div>
                        <div class="col">
                            <label>並び順</label>
                            <input type="number" step="1" name="sort_order" value="{{ $element['sort_order'] ?? 0 }}">
                        </div>
                        <div class="col">
                            <label>有効</label>
                            <div><input type="checkbox" name="active" value="1" @if(!empty($element['active'])) checked @endif> 有効</div>
                        </div>
                    </div>
                    <div style="margin-top:8px;">
                        <label>メモ</label>
                        <textarea name="memo">{{ $element['memo'] ?? '' }}</textarea>
                    </div>
                    <div class="actions" style="margin-top:8px;">
                        <button type="submit">要素更新申請</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('work.labor-costs.elements.edit-request.delete', $elementId) }}" style="margin-top:6px;">
                    @csrf
                    <input type="hidden" name="_mode" value="submit">
                    <button type="submit" onclick="return confirm('この要素の削除申請を送信しますか？')">要素削除申請</button>
                </form>
            </div>
        @empty
            <div class="muted" style="margin-bottom:8px;">要素はありません。</div>
        @endforelse

        <h4 style="margin:12px 0 8px;">要素を追加</h4>
        <form method="POST" action="{{ route('work.labor-costs.elements.edit-request.create') }}">
            @csrf
            <input type="hidden" name="_mode" value="submit">
            <input type="hidden" name="process_id" value="{{ $processId }}">
            <div class="row">
                <div class="col">
                    <label>要素コード</label>
                    <input type="text" name="element_code" placeholder="CURE">
                </div>
                <div class="col">
                    <label>要素名</label>
                    <input type="text" name="name" placeholder="硬化">
                </div>
                <div class="col">
                    <label>作業時間（分）</label>
                    <input type="number" step="0.000001" min="0" name="work_minutes" value="0">
                </div>
                <div class="col">
                    <label>係数</label>
                    <input type="number" step="0.000001" min="0" name="activity_coeff" value="1">
                </div>
            </div>
            <div class="row" style="margin-top:8px;">
                <div class="col">
                    <label>1回処理数量</label>
                    <input type="number" min="1" step="1" name="batch_size" value="1">
                </div>
                <div class="col">
                    <label>減価償却費</label>
                    <input type="number" min="0" step="0.01" name="depreciation_amount" value="0">
                </div>
                <div class="col">
                    <label>要素良品率（初期値）</label>
                    <input type="number" min="0.000001" step="0.000001" name="default_yield_rate" value="0.9">
                </div>
                <div class="col">
                    <label>並び順</label>
                    <input type="number" step="1" name="sort_order" value="100">
                </div>
                <div class="col">
                    <label>有効</label>
                    <div><input type="checkbox" name="active" value="1" checked> 有効</div>
                </div>
            </div>
            <div style="margin-top:8px;">
                <label>メモ</label>
                <textarea name="memo"></textarea>
            </div>
            <div class="actions" style="margin-top:8px;">
                <button type="submit">要素作成申請</button>
            </div>
        </form>
    </div>
@endforeach

