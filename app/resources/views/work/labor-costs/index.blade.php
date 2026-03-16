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
            padding: 8px;
            margin-bottom: 10px;
        }
        .labor-card-toggle {
            padding: 0;
            overflow: hidden;
        }
        .labor-card-toggle > summary {
            cursor: pointer;
            list-style: none;
            margin: 0;
            padding: 8px;
        }
        .labor-card-toggle > summary::-webkit-details-marker {
            display: none;
        }
        .labor-card-toggle > summary::before {
            content: "▶";
            margin-right: 6px;
            font-size: 10px;
            display: inline-block;
            transition: transform 0.12s ease;
            color: #6b7280;
        }
        .labor-card-toggle[open] > summary::before {
            transform: rotate(90deg);
        }
        .labor-card-toggle-body {
            border-top: 1px solid #e5e7eb;
            padding: 8px;
        }
        .labor-card h3 {
            margin-top: 0;
        }
        .labor-card-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        .labor-card--process {
            border-left: 4px solid #2563eb;
        }
        .labor-card--settings {
            border-left: 4px solid #d97706;
        }
        .labor-card--rule {
            border-left: 4px solid #059669;
        }
        .labor-kind {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            white-space: nowrap;
        }
        .labor-kind--process {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .labor-kind--element {
            background: #f3f4f6;
            color: #374151;
        }
        .labor-kind--settings {
            background: #fef3c7;
            color: #b45309;
        }
        .labor-kind--rule {
            background: #d1fae5;
            color: #047857;
        }
        .labor-compact-note {
            font-size: 12px;
            color: #6b7280;
        }
        .labor-inline-wrap {
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .labor-inline-form {
            display: grid;
            gap: 6px;
            align-items: start;
            min-width: 980px;
        }
        .labor-field {
            display: grid;
            gap: 2px;
            min-width: 0;
        }
        .labor-field-label {
            font-size: 11px;
            line-height: 1.2;
            color: #4b5563;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .labor-inline-form--process {
            grid-template-columns: 120px 170px 110px 90px 70px 1fr auto;
        }
        .labor-inline-form--element {
            grid-template-columns: 110px 150px 92px 92px 82px 110px 100px 86px 70px 1fr auto;
        }
        .labor-inline-form--rule {
            grid-template-columns: 130px 160px 150px 90px 120px 120px 120px 120px 70px 70px 1fr auto;
        }
        .labor-inline-form--setting {
            grid-template-columns: 180px 1fr auto;
            min-width: 680px;
        }
        .labor-inline-form input[type="text"],
        .labor-inline-form input[type="number"],
        .labor-inline-form select {
            height: 30px;
            box-sizing: border-box;
            width: 100%;
            margin: 0;
            min-width: 0;
        }
        .labor-inline-form .labor-text-input {
            height: 30px;
        }
        .labor-inline-form button {
            height: 30px;
            white-space: nowrap;
        }
        .labor-checkbox-inline {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            font-size: 12px;
            margin-top: 18px;
        }
        .labor-subsection {
            margin-top: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
            padding: 6px;
        }
        .labor-subsection-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .labor-row-stack {
            display: grid;
            gap: 6px;
        }
        .labor-delete-inline {
            margin-top: 4px;
            display: flex;
            justify-content: flex-end;
        }
        .labor-delete-inline button {
            height: 28px;
        }
        .labor-toggle {
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            background: #f8fafc;
            padding: 6px;
            margin-bottom: 10px;
        }
        .labor-toggle > summary {
            cursor: pointer;
            font-weight: 700;
            color: #1f2937;
            list-style: none;
        }
        .labor-toggle > summary::-webkit-details-marker {
            display: none;
        }
        .labor-toggle > summary::before {
            content: "▶";
            margin-right: 6px;
            font-size: 10px;
            display: inline-block;
            transition: transform 0.12s ease;
        }
        .labor-toggle[open] > summary::before {
            transform: rotate(90deg);
        }
        .labor-toggle-body {
            margin-top: 8px;
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
            .labor-inline-form {
                min-width: 780px;
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
