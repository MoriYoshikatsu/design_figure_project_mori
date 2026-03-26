<div class="labor-segment" data-labor-tab-switch>
    <button
        type="button"
        class="labor-segment-btn @if($activeTab === 'processes') is-active @endif"
        data-labor-tab="processes"
        data-labor-tab-url="{{ $processesTabUrl }}"
    >
        <span>工程・要素管理</span>
        <span class="muted">{{ $processCount }}</span>
    </button>
    <button
        type="button"
        class="labor-segment-btn @if($activeTab === 'settings') is-active @endif"
        data-labor-tab="settings"
        data-labor-tab-url="{{ $settingsTabUrl }}"
    >
        <span>全体変数・ルール</span>
        <span class="muted">{{ $ruleCount }}</span>
    </button>
</div>

