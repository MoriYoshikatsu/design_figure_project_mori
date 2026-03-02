@extends('work.layout')

@section('content')
    @php
        $activeTab = in_array(($activeTab ?? 'processes'), ['processes', 'settings'], true) ? $activeTab : 'processes';
        $processes = is_array($processes ?? null) ? $processes : [];
        $rules = is_array($rules ?? null) ? $rules : [];
        $processOptions = is_array($processOptions ?? null) ? $processOptions : [];
        $setting = $setting ?? null;
        $processesTabUrl = route('work.labor-costs.index', ['tab' => 'processes']);
        $settingsTabUrl = route('work.labor-costs.index', ['tab' => 'settings']);
    @endphp

    <style>
        .labor-segment {
            display: flex;
            gap: 8px;
            margin: 10px 0 14px;
        }
        .labor-segment-btn {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            min-width: 220px;
            padding: 8px 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }
        .labor-segment-btn.is-active {
            background: #ecfeff;
            border-color: #0891b2;
            color: #0e7490;
            font-weight: 700;
        }
        .labor-tab-panel[hidden] {
            display: none;
        }
        .labor-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            padding: 10px;
            margin-bottom: 14px;
        }
        .labor-card h3 {
            margin-top: 0;
        }
        .labor-inline-actions {
            display: inline-flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }
        .labor-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }
        @media (max-width: 900px) {
            .labor-segment {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
            .labor-segment-btn {
                width: 100%;
                min-width: 0;
            }
        }
    </style>

    <div data-labor-root data-active-tab="{{ $activeTab }}">
        <h1>作業費管理</h1>
        @include('work.labor-costs._tab_switch', [
            'activeTab' => $activeTab,
            'processesTabUrl' => $processesTabUrl,
            'settingsTabUrl' => $settingsTabUrl,
            'processCount' => count($processes),
            'ruleCount' => count($rules),
        ])

        <section class="labor-tab-panel" data-labor-tab-panel="processes" @if($activeTab !== 'processes') hidden @endif>
            @include('work.labor-costs._processes_tab', [
                'processes' => $processes,
                'processOptions' => $processOptions,
            ])
        </section>

        <section class="labor-tab-panel" data-labor-tab-panel="settings" @if($activeTab !== 'settings') hidden @endif>
            @include('work.labor-costs._settings_rules_tab', [
                'setting' => $setting,
                'rules' => $rules,
                'processOptions' => $processOptions,
            ])
        </section>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-labor-root]');
            if (!root) return;

            const tabButtons = Array.from(root.querySelectorAll('[data-labor-tab]'));
            const panels = {
                processes: root.querySelector('[data-labor-tab-panel="processes"]'),
                settings: root.querySelector('[data-labor-tab-panel="settings"]'),
            };

            const setActive = (tab, replaceUrl) => {
                tabButtons.forEach((button) => {
                    const isActive = button.getAttribute('data-labor-tab') === tab;
                    button.classList.toggle('is-active', isActive);
                });
                Object.entries(panels).forEach(([key, panel]) => {
                    if (!panel) return;
                    panel.hidden = key !== tab;
                });
                root.querySelectorAll('.labor-active-tab-input').forEach((input) => {
                    input.value = tab;
                });

                if (replaceUrl) {
                    const button = tabButtons.find((candidate) => candidate.getAttribute('data-labor-tab') === tab);
                    const nextUrl = button ? button.getAttribute('data-labor-tab-url') : null;
                    if (nextUrl && window.history && typeof window.history.replaceState === 'function') {
                        window.history.replaceState(null, '', nextUrl);
                    }
                }
            };

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const tab = button.getAttribute('data-labor-tab');
                    if (!tab || !panels[tab]) return;
                    setActive(tab, true);
                });
            });

            setActive(root.getAttribute('data-active-tab') || 'processes', false);
        })();
    </script>
@endsection

