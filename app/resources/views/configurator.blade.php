<div>
    <div style="display:flex; gap:16px; padding:16px; align-items:flex-start;">
        <div style="width: 250px; max-height: calc(100vh - 32px); overflow-y: auto; padding-right: 8px;">
            <h1 style="font-weight:700;">MFD変換</h1>
            <div style="margin-top:12px;">
                <label>MFD変換の数（1 ~ 2）</label>
                <input type="number" min="1" max="2" wire:model.live.debounce.200ms="config.mfdCount" style="width:100%;">
            </div>

            <div style="margin-top:12px;">
                <label>チューブの数（0 ~ ファイバ数）</label>
                {{-- <label>(ファイバーの数 = MFD変換の数 + 1)</label> --}}
                <input type="number" min="0" wire:model.live.debounce.200ms="config.tubeCount" style="width:100%;">
            </div>

            <div style="margin-top:12px;">
                <label>スリーブ（MFDごと）</label>
                @foreach(($config['sleeves'] ?? []) as $k => $s)
                    <div style="margin-top:6px;">
                        <div style="font-size:12px;">MFD[{{ $k }}]</div>
                        <select wire:model.live.debounce.500ms="config.sleeves.{{ $k }}.skuCode" style="width:100%;">
                            <option value="">（未選択）</option>
                            @foreach(($skuOptions['sleeve'] ?? []) as $opt)
                                <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <hr style="margin:12px 0;">

            <h2 style="font-weight:700;">各ファイバ</h2>
            @foreach(($config['fibers'] ?? []) as $i => $f)
                <div wire:key="fiber-row-{{ $f['key'] ?? $i }}" style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                    <div>ファイバ[{{ $i }}]</div>
                    <select wire:model.live.debounce.500ms="config.fibers.{{ $i }}.skuCode" style="width:100%;">
                        <option value="">（未選択）</option>
                        @foreach(($skuOptions['fiber'] ?? []) as $opt)
                            <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    <label>長さ(m)</label>
                    <input type="number" step="0.001" wire:model.live.debounce.1000ms="config.fibers.{{ $i }}.lengthM" style="width:100%;">
                    <label>希望許容誤差(m)</label>
                    <input type="number" step="0.001" wire:model.live.debounce.1000ms="config.fibers.{{ $i }}.toleranceM" style="width:100%;">
                </div>
            @endforeach

            <hr style="margin:12px 0;">

            <h2 style="font-weight:700;">各チューブ</h2>
            @foreach(($config['tubes'] ?? []) as $j => $t)
                <div wire:key="tube-row-{{ $t['key'] ?? $j }}" style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                    <div>チューブ[{{ $j }}]</div>

                    <select wire:model.live.debounce.500ms="config.tubes.{{ $j }}.skuCode" style="width:100%;">
                        <option value="">（未選択）</option>
                        @foreach(($skuOptions['tube'] ?? []) as $opt)
                            <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>

                    <label>チューブ左端位置のファイバ番号</label>
                    <input type="number" min="0" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.startFiberIndex" style="width:100%;">

                    <label>そのファイバ左端からの距離(m)</label>
                    <input type="number" step="0.001" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.startOffsetM" style="width:100%;">

                    <label>チューブ右端位置のファイバ番号</label>
                    <input type="number" min="0" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.endFiberIndex" style="width:100%;">

                    <label>そのファイバ左端からの距離(m)</label>
                    <input type="number" step="0.001" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.endOffsetM" style="width:100%;">

                    <label>希望許容誤差(m)</label>
                    <input type="number" step="0.001" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.toleranceM" style="width:100%;">
                </div>
            @endforeach

            <hr style="margin:12px 0;">

            <h2 style="font-weight:700;">コネクタ</h2>
            <div style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                <label>必要な位置</label>
                <select wire:model.live.debounce.300ms="config.connectors.mode" style="width:100%;">
                    <option value="none">なし</option>
                    <option value="left">全体の左端</option>
                    <option value="right">全体の右端</option>
                    <option value="both">全体の両端</option>
                </select>

                <label>全体の左端</label>
                <select wire:model.live.debounce.500ms="config.connectors.leftSkuCode" style="width:100%;">
                    <option value="">（未選択）</option>
                    @foreach(($skuOptions['connector'] ?? []) as $opt)
                        <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>

                <label style="margin-top:8px; display:block;">全体の右端</label>
                <select wire:model.live.debounce.500ms="config.connectors.rightSkuCode" style="width:100%;">
                    <option value="">（未選択）</option>
                    @foreach(($skuOptions['connector'] ?? []) as $opt)
                        <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>

            @if($quoteEditId)
                <hr style="margin:12px 0;">

                <h2 style="font-weight:700;">概要カード表示項目</h2>
                <div style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                    <div style="display:grid; grid-template-columns:1fr; gap:4px;">
                        @foreach(($summaryFieldOptions ?? []) as $key => $label)
                            <label style="display:flex; align-items:center; gap:6px;">
                                <input type="checkbox" wire:model="summaryFields" value="{{ $key }}">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <h2 style="font-weight:700;">メモ</h2>
            <div style="margin-top:12px;">
                <label>詳細な希望仕様など</label>
                <textarea wire:model.live.debounce.600ms="memo" rows="4" style="width:100%;"></textarea>
            </div>
        </div>

        <div style="flex:1;">
            @if(!$quoteEditId)
                <button wire:click="newSession" type="button">新規ファイバ作成（新規セッション）</button>
            @endif
            @if($quoteEditId)
                <button type="button" wire:click="requestQuoteEdit">
                    見積変更申請を送信
                </button>
            @else
                <button type="button" wire:click="issueQuote">
                    仕様書発行
                </button>
            @endif
            {{-- 保存中 --}}
            @if($isSaving)
                <span wire:loading wire:target="saveNow">保存中…</span>
            @else
                {{-- 失敗 --}}
                @if($saveError)
                    <span style="color:#dc2626; font-weight:700;">保存失敗…</span>
                    <span style="color:#dc2626;">{{ $saveError }}</span>
                    <button type="button" wire:click="saveNow">再試行</button>
                @else
                    {{-- 通常 --}}
                    <span>
                        状態：{{ $dirty ? '未保存' : '保存済み' }}
                        @if($saveStatus)（{{ $saveStatus }}）@endif
                    </span>
                @endif
            @endif

            <h2 style="font-weight:700;">プレビュー</h2>
            <div style="border:1px solid #ddd; padding:12px;">
                {!! $svg !!}
            </div>

            <hr style="margin:12px 0;">

            <h2 style="font-weight:700;">エラー</h2>
            <ul>
                @foreach($errors as $e)
                    <li><b>{{ $e['path'] ?? '' }}</b>：{{ $e['message'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
<script>
document.addEventListener('livewire:init', () => {
    const autosaveUrl = @json(route('configurator.autosave'));
    const csrfToken = @json(csrf_token());
    const componentId = @json($this->getId());

    const getComponent = () => {
        if (window.Livewire && typeof window.Livewire.find === 'function') {
            return window.Livewire.find(componentId);
        }
        return null;
    };

    // ページ離脱（beforeunload：離脱検知）
    window.addEventListener('beforeunload', () => {
        const c = getComponent();
        if (!c) return;

        const dirty = c.get('dirty');
        if (!dirty) return;

        const sessionId = c.get('sessionId');
        if (!sessionId || Number(sessionId) <= 0) return;
        const config = c.get('config');
        const memo = c.get('memo');

        const fd = new FormData();
        fd.append('_token', csrfToken);                 // CSRF（改ざん防止）
        fd.append('session_id', String(sessionId));     // 保存先セッション
        fd.append('config_json', JSON.stringify(config || {})); // config本体
        fd.append('memo', memo ?? '');

        navigator.sendBeacon(autosaveUrl, fd);          // sendBeacon（離脱送信）
    });

    // 追加で安全策：タブ非表示（visibilitychange：表示切替）でも保存
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'hidden') return;

        const c = getComponent();
        if (!c) return;

        const dirty = c.get('dirty');
        if (!dirty) return;

        const sessionId = c.get('sessionId');
        if (!sessionId || Number(sessionId) <= 0) return;
        const config = c.get('config');
        const memo = c.get('memo');

        const fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('session_id', String(sessionId));
        fd.append('config_json', JSON.stringify(config || {}));
        fd.append('memo', memo ?? '');

        navigator.sendBeacon(autosaveUrl, fd);
    });
});
</script>
