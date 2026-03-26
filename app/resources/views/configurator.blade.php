<div>
    @php
        $processType = strtoupper((string)($config['processType'] ?? 'MFD'));
        $isTecMode = in_array($processType, ['TEC', 'TEC20', 'TEC30', 'TEC20_HP', 'TEC30_HP'], true);
        $isMfdMode = !$isTecMode;
        $fiberCount = max(1, count($config['fibers'] ?? []));
        $maxFiberIndex = max(0, $fiberCount - 1);
        $publicEnglish = !$quoteEditId;
        $tr = static fn(string $ja, string $en): string => $publicEnglish ? $en : $ja;
        $componentErrors = is_array($this->errors ?? null) ? $this->errors : [];
    @endphp
    <div style="display:flex; gap:0; padding:16px; align-items:flex-start;">
        <div
            id="configurator-left-panel"
            wire:ignore.self
            style="width: 280px; min-width: 220px; max-width: calc(100vw - 280px); max-height: calc(100vh - 32px); overflow-y: auto; overflow-x: hidden; padding-right: 8px; flex: 0 0 auto;"
        >
            <h1 style="font-weight:700;">{{ $tr('コンフィグレーター', 'Configurator') }}</h1>
            <div style="margin-top:12px;">
                <label>{{ $tr('加工種別', 'Process Type') }}</label>
                <select wire:model.live.debounce.200ms="config.processType" style="width:100%;">
                    <option value="MFD">MFD</option>
                    <option value="TEC">TEC</option>
                    <option value="TEC20">TEC20</option>
                    <option value="TEC30">TEC30</option>
                    <option value="TEC20_HP">TEC20_HP</option>
                    <option value="TEC30_HP">TEC30_HP</option>
                </select>
            </div>

            @if($isTecMode)
                <div style="margin-top:12px;">
                    <label>{{ $tr('TEC位置', 'TEC Position') }}</label>
                    <select wire:model.live.debounce.200ms="config.tecSide" style="width:100%;">
                        <option value="">{{ $tr('（選択してください）', '(Please select)') }}</option>
                        <option value="left">{{ $tr('左端', 'Left End') }}</option>
                        <option value="right">{{ $tr('右端', 'Right End') }}</option>
                        <option value="both">{{ $tr('両端', 'Both Ends') }}</option>
                    </select>
                </div>

                @if(in_array(($config['tecSide'] ?? null), ['left', 'both'], true))
                    <div style="margin-top:12px;">
                        <label>{{ $tr('左端TEC種別', 'Left TEC Type') }}</label>
                        <select wire:model.live.debounce.200ms="config.tecLeftProcessType" style="width:100%;">
                            <option value="">{{ $tr('（選択してください）', '(Please select)') }}</option>
                            <option value="TEC20">TEC20</option>
                            <option value="TEC30">TEC30</option>
                            <option value="TEC20_HP">TEC20_HP</option>
                            <option value="TEC30_HP">TEC30_HP</option>
                        </select>
                    </div>
                @endif

                @if(in_array(($config['tecSide'] ?? null), ['right', 'both'], true))
                    <div style="margin-top:12px;">
                        <label>{{ $tr('右端TEC種別', 'Right TEC Type') }}</label>
                        <select wire:model.live.debounce.200ms="config.tecRightProcessType" style="width:100%;">
                            <option value="">{{ $tr('（選択してください）', '(Please select)') }}</option>
                            <option value="TEC20">TEC20</option>
                            <option value="TEC30">TEC30</option>
                            <option value="TEC20_HP">TEC20_HP</option>
                            <option value="TEC30_HP">TEC30_HP</option>
                        </select>
                    </div>
                @endif
            @endif

            @if($isMfdMode)
                <div style="margin-top:12px;">
                    <label>{{ $tr('スリーブ', 'Sleeves') }}</label>
                    @foreach(($config['sleeves'] ?? []) as $k => $s)
                        <div style="margin-top:6px;">
                            <!-- <div style="font-size:12px;">MFD[{{ $k }}]</div> -->
                            <select wire:model.live.debounce.500ms="config.sleeves.{{ $k }}.skuCode" style="width:100%;">
                                <option value="">{{ $tr('（未選択）', '(Not selected)') }}</option>
                                @foreach(($skuOptions['sleeve'] ?? []) as $opt)
                                    <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>

                <hr style="margin:12px 0;">
            @endif

            <div style="margin-top:12px;">
                <label>{{ $tr('チューブの数（0〜2）', 'Tube Count (0-2)') }}</label>
                <input type="number" min="0" max="2" wire:model.live.debounce.200ms="config.tubeCount" style="width:100%;">
            </div>

            <h2 style="font-weight:700;">{{ $tr('ファイバ（公差±10cm）', 'Fibers (Tolerance +/-0.1m)') }}</h2>
            @foreach(($config['fibers'] ?? []) as $i => $f)
                <div wire:key="fiber-row-{{ $f['key'] ?? $i }}" style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                    <div>{{ $tr('ファイバ', 'Fiber') }}[{{ $i }}]</div>
                    <select wire:model.live.debounce.500ms="config.fibers.{{ $i }}.skuCode" style="width:100%;">
                        <option value="">{{ $tr('（未選択）', '(Not selected)') }}</option>
                        @foreach(($skuOptions['fiber'] ?? []) as $opt)
                            <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    <label>{{ $tr('長さ(m)（0.2〜2.0m）', 'Length (m) (0.2-2.0m)') }}</label>
                    <input type="number" step="0.1" min="0.2" max="2.0" wire:model.live.debounce.1000ms="config.fibers.{{ $i }}.lengthM" style="width:100%;">
                </div>
            @endforeach

            <hr style="margin:12px 0;">

            <h2 style="font-weight:700;">{{ $tr('チューブ（公差±1cm）', 'Tubes (Tolerance +/-0.01m)') }}</h2>
            @foreach(($config['tubes'] ?? []) as $j => $t)
                <div wire:key="tube-row-{{ $t['key'] ?? $j }}" style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                    <div>{{ $tr('チューブ', 'Tube') }}[{{ $j }}]</div>

                    <select wire:model.live.debounce.500ms="config.tubes.{{ $j }}.skuCode" style="width:100%;">
                        <option value="">{{ $tr('（未選択）', '(Not selected)') }}</option>
                        @foreach(($skuOptions['tube'] ?? []) as $opt)
                            <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>

                    <label>{{ $tr('チューブ左端位置のファイバ番号', 'Fiber Index at Tube Left End') }}</label>
                    <input type="number" min="0" max="{{ $maxFiberIndex }}" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.startFiberIndex" style="width:100%;">

                    <label>{{ $tr('そのファイバ左端からの距離(m)', 'Distance from That Fiber\'s Left End (m)') }}</label>
                    <input type="number" step="0.01" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.startOffsetM" style="width:100%;">

                    <label>{{ $tr('チューブ右端位置のファイバ番号', 'Fiber Index at Tube Right End') }}</label>
                    <input type="number" min="0" max="{{ $maxFiberIndex }}" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.endFiberIndex" style="width:100%;">

                    <label>{{ $tr('そのファイバ左端からの距離(m)', 'Distance from That Fiber\'s Left End (m)') }}</label>
                    <input type="number" step="0.01" wire:model.live.debounce.1000ms="config.tubes.{{ $j }}.endOffsetM" style="width:100%;">
                </div>
            @endforeach

            <hr style="margin:12px 0;">

            <h2 style="font-weight:700;">{{ $tr('コネクタ', 'Connectors') }}</h2>
            <div style="border:1px solid #ddd; padding:8px; margin-top:8px;">
                <label>{{ $tr('必要な位置', 'Required Position') }}</label>
                <select wire:model.live.debounce.300ms="config.connectors.mode" style="width:100%;">
                    <option value="none">{{ $tr('なし', 'None') }}</option>
                    <option value="left">{{ $tr('全体の左端', 'System Left End') }}</option>
                    <option value="right">{{ $tr('全体の右端', 'System Right End') }}</option>
                    <option value="both">{{ $tr('全体の両端', 'Both System Ends') }}</option>
                </select>

                <label>{{ $tr('全体の左端', 'System Left End') }}</label>
                <select wire:model.live.debounce.500ms="config.connectors.leftSkuCode" style="width:100%;">
                    <option value="">{{ $tr('（未選択）', '(Not selected)') }}</option>
                    @foreach(($skuOptions['connector'] ?? []) as $opt)
                        <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>

                <label style="margin-top:8px; display:block;">{{ $tr('全体の右端', 'System Right End') }}</label>
                <select wire:model.live.debounce.500ms="config.connectors.rightSkuCode" style="width:100%;">
                    <option value="">{{ $tr('（未選択）', '(Not selected)') }}</option>
                    @foreach(($skuOptions['connector'] ?? []) as $opt)
                        <option value="{{ $opt['code'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <hr style="margin:12px 0;">
            <h2 style="font-weight:700;">{{ $tr('希望注文数量', 'Desired Order Quantity') }}</h2>
            <input type="number" min="1" step="1" wire:model.live.debounce.300ms="orderQty" style="width:100%;">

            <h2 style="font-weight:700;">{{ $tr('メモ', 'Notes') }}</h2>
            <div style="margin-top:12px;">
                <label>{{ $tr('詳細な希望仕様、送付先住所など', 'Detailed Requirements, Shipping Address, etc.') }}</label>
                <textarea wire:model.live.debounce.600ms="memo" rows="4" style="width:100%;"></textarea>
            </div>
        </div>

        <button
            id="configurator-panel-resizer"
            type="button"
            aria-label="{{ $tr('左パネル幅を調整', 'Adjust Left Panel Width') }}"
            title="{{ $tr('ドラッグして左パネルの幅を調整', 'Drag to Resize the Left Panel') }}"
            style="width:10px; min-width:10px; height: calc(100vh - 32px); margin:0 8px; padding:0; border:0; border-radius:999px; cursor:col-resize; background:#d1d5db; flex:0 0 auto;"
        ></button>

        <div style="flex:1; min-width:0;">
            @if(!$quoteEditId)
                <button wire:click="newSession" type="button">{{ $tr('新規ファイバ作成（新規セッション）', 'Create New Fiber (New Session)') }}</button>
            @endif
            @if($quoteEditId)
                <button
                    type="button"
                    wire:click="requestQuoteEdit"
                    @disabled(!empty($componentErrors))
                    style="{{ !empty($componentErrors) ? 'opacity:0.5; cursor:not-allowed;' : '' }}"
                    title="{{ !empty($componentErrors) ? $tr('エラーを解消すると見積変更を申請できます', 'Resolve the errors to submit the quote update.') : '' }}"
                >
                    見積変更を申請
                </button>
                @if(!empty($componentErrors))
                    <div style="color:#dc2626; font-size:12px; margin-top:4px;">
                        {{ $tr('エラーがあるため、見積変更は申請できません。', 'The quote update cannot be submitted while errors remain.') }}
                    </div>
                @endif
            @else
                <button
                    type="button"
                    wire:click="issueQuote"
                    @disabled(!empty($componentErrors))
                    style="{{ !empty($componentErrors) ? 'opacity:0.5; cursor:not-allowed;' : '' }}"
                    title="{{ !empty($componentErrors) ? $tr('エラーを解消すると仕様書発行できます', 'Resolve the errors to issue the spec sheet') : '' }}"
                >
                    {{ $tr('仕様書発行', 'Issue Spec Sheet') }}
                </button>
                @if(!empty($componentErrors))
                    <div style="color:#dc2626; font-size:12px; margin-top:4px;">
                        {{ $tr('エラーがあるため、仕様書発行はできません。', 'The spec sheet cannot be issued while errors remain.') }}
                    </div>
                @endif
            @endif
            {{-- 保存中 --}}
            @if($isSaving)
                <span wire:loading wire:target="saveNow">{{ $tr('保存中…', 'Saving...') }}</span>
            @else
                {{-- 失敗 --}}
                @if($saveError)
                    <span style="color:#dc2626; font-weight:700;">{{ $tr('保存失敗…', 'Save failed...') }}</span>
                    <span style="color:#dc2626;">{{ $saveError }}</span>
                    <button type="button" wire:click="saveNow">{{ $tr('再試行', 'Retry') }}</button>
                @else
                    {{-- 通常 --}}
                    <span>
                        {{ $dirty ? $tr('未保存', 'Unsaved') : $tr('保存済み', 'Saved') }}
                        @if($saveStatus){{ $publicEnglish ? ' (' : '（' }}{{ $saveStatus }}{{ $publicEnglish ? ')' : '）' }}@endif
                    </span>
                @endif
            @endif

            @if($quoteEditId)
                <div style="margin-top:12px; border:1px solid {{ ($quoteEditApprovalRequired ?? true) ? '#fcd34d' : '#86efac' }}; border-radius:8px; padding:12px; background:{{ ($quoteEditApprovalRequired ?? true) ? '#fffbeb' : '#f0fdf4' }};">
                    <div style="font-weight:700;">
                        現在の反映方式:
                        {{ ($quoteEditApprovalRequired ?? true) ? '承認後に反映' : '即時反映' }}
                    </div>
                    <div style="font-size:12px; color:#475569; margin-top:4px;">
                        判定対象はログイン中アカウント #{{ $quoteEditDecisionAccountId ?? '?' }} の「見積・仕様書」設定です。
                    </div>
                    @if($quoteEditApprovalRequired ?? true)
                        <div style="font-size:12px; color:#475569; margin-top:4px;">
                            直前に設定を外していても、その設定変更自体が承認待ちなら、この見積編集はまだ即時反映になりません。
                        </div>
                    @endif
                </div>

                <div style="margin-top:12px; border:1px solid #d1d5db; border-radius:8px; padding:12px; background:#f8fafc;">
                    <h2 style="font-weight:700; margin:0;">見積編集用設定</h2>
                    <details style="border:1px solid #e5e7eb; border-radius:6px; background:#fff; padding:8px; margin-bottom:8px;" @if(($specSheetNumber ?? null) || trim((string)($editComment ?? '')) !== '') open @endif>
                        <summary style="cursor:pointer; font-weight:700;">仕様書番号・編集コメント</summary>
                        <div style="margin-top:8px; display:grid; gap:8px;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:4px;">仕様書番号</label>
                                <div style="font-size:12px; color:#475569; margin-bottom:6px;">プレビュー右上とPDFに表示する番号</div>
                                <input
                                    type="text"
                                    maxlength="255"
                                    autocomplete="off"
                                    wire:model.live.debounce.600ms="specSheetNumber"
                                    placeholder="例）SPEC-2026-001"
                                    style="width:100%;"
                                >
                            </div>

                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:4px;">編集した理由・背景のコメント（任意）</label>
                                <textarea
                                    wire:model.live.debounce.180000ms="editComment"
                                    rows="2"
                                    placeholder="例）顧客要望により仕様変更、金額調整"
                                    style="width:100%;"
                                ></textarea>
                            </div>
                        </div>
                    </details>

                    <details style="border:1px solid #e5e7eb; border-radius:6px; background:#fff; padding:8px; margin-bottom:8px;">
                        <summary style="cursor:pointer; font-weight:700;">概要カード表示項目</summary>
                        <div style="margin-top:8px; display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:6px;">
                            @foreach(($summaryFieldOptions ?? []) as $key => $label)
                                <label style="display:flex; align-items:center; gap:6px;">
                                    <input type="checkbox" wire:model="summaryFields" value="{{ $key }}">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <details style="border:1px solid #e5e7eb; border-radius:6px; background:#fff; padding:8px; margin-bottom:8px;">
                        <summary style="cursor:pointer; font-weight:700;">見積計算入力（従業員向け）</summary>
                        <div style="margin-top:8px; display:grid; gap:8px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
                            <div>
                                <label>固定性経費</label>
                                <input type="number" step="1" wire:model.live.debounce.180000ms="fixedCost" style="width:100%;">
                            </div>
                            <div>
                                <label>管理費係数</label>
                                <input type="number" step="0.0001" wire:model.live.debounce.180000ms="managementFactor" style="width:100%;">
                            </div>
                            <div>
                                <label>数量ディスカウント係数</label>
                                <input type="number" step="0.0001" wire:model.live.debounce.180000ms="qtyDiscountFactor" style="width:100%;">
                            </div>
                            <div>
                                <label>顧客別仕切</label>
                                <input type="number" step="0.0001" wire:model.live.debounce.180000ms="customerFactor" style="width:100%;">
                            </div>
                            <div>
                                <label>荷造運賃</label>
                                <input type="number" step="1" wire:model.live.debounce.180000ms="freightAmount" style="width:100%;">
                            </div>
                            <div>
                                <label>任意の値引き（0以下）</label>
                                <input type="number" step="1" max="0" wire:model.live.debounce.180000ms="manualDiscountAmount" style="width:100%;">
                            </div>
                            <div>
                                <label>取引区分</label>
                                <select wire:model.live.debounce.180000ms="tradeScope" style="width:100%;">
                                    <option value="DOMESTIC">DOMESTIC</option>
                                    <option value="OVERSEAS">OVERSEAS</option>
                                </select>
                            </div>
                            <div>
                                <label>税率（上書き可）</label>
                                <input type="number" step="0.0001" wire:model.live.debounce.180000ms="taxRate" style="width:100%;">
                            </div>
                        </div>
                    </details>

                    <details style="border:1px solid #e5e7eb; border-radius:6px; background:#fff; padding:8px;">
                        <summary style="cursor:pointer; font-weight:700;">作業費歩留まり上書き（見積編集）</summary>
                        <div style="margin-top:8px;">
                            <div class="muted" style="margin-bottom:8px;">
                                工程に入力がある場合は要素入力より工程入力を優先します。工程/要素ともに「注文数量」「実投入数」を両方入力した場合、良品率は 注文数量÷実投入数 を採用します。
                            </div>
                            @php
                                $visibleLaborOverrideRows = array_values(array_filter(
                                    $laborOverrideRows ?? [],
                                    static fn($row) => strtoupper((string)($row['process_code'] ?? '')) !== 'MFD'
                                ));
                            @endphp

                            @if(empty($visibleLaborOverrideRows))
                                <div class="muted">自動選択された工程がありません。</div>
                            @else
                                @foreach($visibleLaborOverrideRows as $process)
                                    @php
                                        $processCode = (string)($process['process_code'] ?? '');
                                        $processName = (string)($process['process_name'] ?? '');
                                        $processDefaultYield = $process['yield_rate_default'] ?? null;
                                        $elements = is_array($process['elements'] ?? null) ? $process['elements'] : [];
                                    @endphp
                                    <div style="border:1px solid #e5e7eb; border-radius:6px; padding:8px; margin-bottom:10px;">
                                        <div style="font-weight:700; margin-bottom:6px;">{{ $processName }}（{{ $processCode }}）</div>
                                        <div class="muted" style="font-size:12px; margin-bottom:8px;">
                                            工程初期良品率: {{ $processDefaultYield !== null ? $processDefaultYield : '-' }}
                                        </div>
                                        <div style="display:grid; gap:8px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
                                            <div>
                                                <label>工程良品率（直入力）</label>
                                                <input type="number" step="0.000001" min="0.000001" wire:model.live.debounce.180000ms="laborOverrides.processes.{{ $processCode }}.yield_rate" style="width:100%;">
                                            </div>
                                            <div>
                                                <label>工程 注文数量</label>
                                                <input type="number" step="1" min="1" wire:model.live.debounce.180000ms="laborOverrides.processes.{{ $processCode }}.order_qty" style="width:100%;">
                                            </div>
                                            <div>
                                                <label>工程 実投入数</label>
                                                <input type="number" step="1" min="1" wire:model.live.debounce.180000ms="laborOverrides.processes.{{ $processCode }}.actual_input_qty" style="width:100%;">
                                            </div>
                                        </div>

                                        @if(!empty($elements))
                                            <div style="margin-top:10px; border-top:1px solid #f0f0f0; padding-top:8px;">
                                                @foreach($elements as $element)
                                                    @php
                                                        $elementCode = (string)($element['element_code'] ?? '');
                                                        $elementName = (string)($element['element_name'] ?? '');
                                                        $elementDefaultYield = $element['yield_rate_default'] ?? null;
                                                    @endphp
                                                    <div style="border:1px dashed #d1d5db; border-radius:6px; padding:6px; margin-bottom:8px;">
                                                        <div style="font-size:12px; font-weight:700;">{{ $elementName }}（{{ $elementCode }}）</div>
                                                        <div class="muted" style="font-size:11px; margin-bottom:6px;">
                                                            要素初期良品率: {{ $elementDefaultYield !== null ? $elementDefaultYield : '-' }}
                                                        </div>
                                                        <div style="display:grid; gap:8px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
                                                            <div>
                                                                <label>要素良品率（直入力）</label>
                                                                <input type="number" step="0.000001" min="0.000001" wire:model.live.debounce.180000ms="laborOverrides.elements.{{ $processCode }}.{{ $elementCode }}.yield_rate" style="width:100%;">
                                                            </div>
                                                            <div>
                                                                <label>要素 注文数量</label>
                                                                <input type="number" step="1" min="1" wire:model.live.debounce.180000ms="laborOverrides.elements.{{ $processCode }}.{{ $elementCode }}.order_qty" style="width:100%;">
                                                            </div>
                                                            <div>
                                                                <label>要素 実投入数</label>
                                                                <input type="number" step="1" min="1" wire:model.live.debounce.180000ms="laborOverrides.elements.{{ $processCode }}.{{ $elementCode }}.actual_input_qty" style="width:100%;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </details>
                </div>
            @endif

            <h2 style="font-weight:700;">{{ $tr('プレビュー', 'Preview') }}</h2>
            <div style="border:1px solid #ddd; padding:12px;">
                {!! $svg !!}
            </div>

            @if(!empty($componentErrors))
                <hr style="margin:12px 0;">

                <h2 style="font-weight:700;">{{ $tr('エラー', 'Error') }}</h2>
                <ul>
                    @foreach($componentErrors as $e)
                        <li><b>{{ $e['path'] ?? '' }}</b>{{ $publicEnglish ? ': ' : '：' }}{{ $this->displayErrorMessage($e) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
<script>
document.addEventListener('livewire:init', () => {
    const autosaveUrl = @json(route('configurator.autosave'));
    const csrfToken = @json(csrf_token());
    const componentId = @json($this->getId());
    const panelStorageKey = 'configurator.left_panel_width.v1';
    const panelMinWidth = 220;
    const panelViewportPadding = 280;
    const resizerState = window.__configuratorPanelResizerState || (window.__configuratorPanelResizerState = {
        initialized: false,
        resizeBound: false,
    });

    const getComponent = () => {
        if (window.Livewire && typeof window.Livewire.find === 'function') {
            return window.Livewire.find(componentId);
        }
        return null;
    };

    const getPanelWidthMax = () => {
        return Math.max(panelMinWidth, window.innerWidth - panelViewportPadding);
    };

    const clampPanelWidth = (value) => {
        const width = Number(value);
        if (!Number.isFinite(width)) return panelMinWidth;
        return Math.max(panelMinWidth, Math.min(getPanelWidthMax(), Math.round(width)));
    };

    const persistPanelWidth = (value) => {
        try {
            window.localStorage.setItem(panelStorageKey, String(value));
        } catch (e) {
            // ignore storage errors
        }
    };

    const restorePanelWidth = () => {
        try {
            const raw = window.localStorage.getItem(panelStorageKey);
            if (raw === null) return null;
            const parsed = Number(raw);
            return Number.isFinite(parsed) ? parsed : null;
        } catch (e) {
            return null;
        }
    };

    const applyPanelWidth = (panel, width) => {
        if (!panel) return;
        const clamped = clampPanelWidth(width);
        panel.style.width = `${clamped}px`;
        persistPanelWidth(clamped);
    };

    const initPanelResizer = () => {
        const panel = document.getElementById('configurator-left-panel');
        const resizer = document.getElementById('configurator-panel-resizer');
        if (!panel || !resizer || resizer.dataset.initialized === '1') return;

        const saved = restorePanelWidth();
        if (saved !== null) {
            applyPanelWidth(panel, saved);
        } else {
            applyPanelWidth(panel, panel.getBoundingClientRect().width || 280);
        }

        let dragging = false;
        let pointerId = null;

        const start = (event) => {
            dragging = true;
            pointerId = event.pointerId;
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'col-resize';
            if (typeof resizer.setPointerCapture === 'function') {
                try {
                    resizer.setPointerCapture(pointerId);
                } catch (e) {
                    // ignore unsupported states
                }
            }
        };

        const move = (event) => {
            if (!dragging) return;
            const panelLeft = panel.getBoundingClientRect().left;
            applyPanelWidth(panel, event.clientX - panelLeft);
        };

        const stop = () => {
            if (!dragging) return;
            dragging = false;
            document.body.style.userSelect = '';
            document.body.style.cursor = '';
            if (pointerId !== null && typeof resizer.releasePointerCapture === 'function') {
                try {
                    resizer.releasePointerCapture(pointerId);
                } catch (e) {
                    // ignore unsupported states
                }
            }
            pointerId = null;
        };

        resizer.addEventListener('pointerdown', start);
        resizer.addEventListener('pointermove', move);
        resizer.addEventListener('pointerup', stop);
        resizer.addEventListener('pointercancel', stop);
        resizer.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            const currentWidth = panel.getBoundingClientRect().width;
            const delta = event.shiftKey ? 40 : 16;
            const next = event.key === 'ArrowLeft' ? currentWidth - delta : currentWidth + delta;
            applyPanelWidth(panel, next);
        });

        resizer.dataset.initialized = '1';
        resizerState.initialized = true;
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
        const specSheetNumber = c.get('specSheetNumber');

        const fd = new FormData();
        fd.append('_token', csrfToken);                 // CSRF（改ざん防止）
        fd.append('session_id', String(sessionId));     // 保存先セッション
        fd.append('config_json', JSON.stringify(config || {})); // config本体
        fd.append('memo', memo ?? '');
        fd.append('spec_sheet_number', specSheetNumber ?? '');

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
        const specSheetNumber = c.get('specSheetNumber');

        const fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('session_id', String(sessionId));
        fd.append('config_json', JSON.stringify(config || {}));
        fd.append('memo', memo ?? '');
        fd.append('spec_sheet_number', specSheetNumber ?? '');

        navigator.sendBeacon(autosaveUrl, fd);
    });

    if (!resizerState.resizeBound) {
        window.addEventListener('resize', () => {
            const panel = document.getElementById('configurator-left-panel');
            if (!panel) return;
            applyPanelWidth(panel, panel.getBoundingClientRect().width);
        });
        resizerState.resizeBound = true;
    }

    initPanelResizer();
});
</script>
