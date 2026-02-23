@extends('work.layout')

@section('content')
    @php
        $skuPanel = is_array($skuPanel ?? null) ? $skuPanel : [];
        $priceBookPanel = is_array($priceBookPanel ?? null) ? $priceBookPanel : [];
        $skuCount = is_countable($skuPanel['skus'] ?? null) ? count($skuPanel['skus']) : 0;
        $priceBookCount = is_countable($priceBookPanel['books'] ?? null) ? count($priceBookPanel['books']) : 0;
        $entryRouteName = (string)($entryRouteName ?? 'work.skus.index');
        $baseQuery = [
            'sku' => is_array($skuFilters ?? null) ? $skuFilters : [],
            'pb' => is_array($priceBookFilters ?? null) ? $priceBookFilters : [],
        ];
        $skuTabUrl = route($entryRouteName, array_merge($baseQuery, ['tab' => 'skus']));
        $priceBookTabUrl = route($entryRouteName, array_merge($baseQuery, ['tab' => 'price_books']));
    @endphp

    <style>
        .catalog-segment {
            display: flex;
            gap: 8px;
            margin: 8px 0 14px;
        }
        .catalog-segment-btn {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            min-width: 180px;
            padding: 8px 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }
        .catalog-segment-btn.is-active {
            background: #ecfeff;
            border-color: #0891b2;
            color: #0e7490;
            font-weight: 700;
        }
        .catalog-segment-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .catalog-segment-count {
            font-size: 12px;
            color: #6b7280;
        }
        .catalog-segment-note {
            font-size: 11px;
            color: #b45309;
        }
        .catalog-panel[hidden] {
            display: none;
        }
        .catalog-detail-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            z-index: 60;
        }
        .catalog-detail-drawer {
            position: fixed;
            right: 0;
            top: 0;
            width: min(520px, 95vw);
            height: 100vh;
            background: #fff;
            border-left: 1px solid #d1d5db;
            box-shadow: -10px 0 30px rgba(15, 23, 42, 0.18);
            padding: 16px;
            overflow-y: auto;
            z-index: 61;
        }
        .catalog-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 12px;
        }
        .catalog-detail-status {
            margin-top: 10px;
            margin-bottom: 12px;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid transparent;
            font-weight: 700;
        }
        .catalog-detail-status.is-normal {
            display: none;
        }
        .catalog-detail-status.is-pending {
            background: #fef9c3;
            border-color: #facc15;
            color: #854d0e;
        }
        .catalog-detail-status.is-danger {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .catalog-detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .catalog-detail-table th,
        .catalog-detail-table td {
            border: 1px solid #e5e7eb;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        .catalog-detail-table th {
            background: #f8fafc;
            width: 35%;
        }
        @media (max-width: 900px) {
            .catalog-segment {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
            .catalog-segment-btn {
                min-width: 0;
                width: 100%;
            }
            .catalog-detail-drawer {
                width: 100vw;
            }
        }
    </style>

    <div data-catalog-root data-active-tab="{{ $activeTab }}">
        <h1>パーツ・価格管理</h1>

        @include('work.catalog._tab_switch', [
            'activeTab' => $activeTab,
            'canAccessSkus' => (bool)$canAccessSkus,
            'canAccessPriceBooks' => (bool)$canAccessPriceBooks,
            'skuCount' => $skuCount,
            'priceBookCount' => $priceBookCount,
            'skuTabUrl' => $skuTabUrl,
            'priceBookTabUrl' => $priceBookTabUrl,
        ])

        <section class="catalog-panel" data-catalog-panel="skus" @if($activeTab !== 'skus') hidden @endif>
            @if($canAccessSkus)
                @include('work.catalog._sku_panel', [
                    'indexRouteName' => $entryRouteName,
                    'skus' => $skuPanel['skus'] ?? collect(),
                    'filters' => $skuPanel['filters'] ?? [],
                    'categories' => $skuPanel['categories'] ?? [],
                    'presenceOptions' => $skuPanel['presenceOptions'] ?? [],
                    'priceBookFilters' => $priceBookFilters,
                ])
            @else
                <div class="muted" style="margin:10px 0;">SKU管理の閲覧権限がありません。</div>
            @endif
        </section>

        <section class="catalog-panel" data-catalog-panel="price_books" @if($activeTab !== 'price_books') hidden @endif>
            @if($canAccessPriceBooks)
                @include('work.catalog._price_book_panel', [
                    'indexRouteName' => $entryRouteName,
                    'books' => $priceBookPanel['books'] ?? collect(),
                    'filters' => $priceBookPanel['filters'] ?? [],
                    'currencyOptions' => $priceBookPanel['currencyOptions'] ?? [],
                    'periodOptions' => $priceBookPanel['periodOptions'] ?? [],
                    'presenceOptions' => $priceBookPanel['presenceOptions'] ?? [],
                    'skuFilters' => $skuFilters,
                ])
            @else
                <div class="muted" style="margin:10px 0;">パーツ価格表の閲覧権限がありません。</div>
            @endif
        </section>

        @include('work.catalog._detail_drawer', ['activeTab' => $activeTab])
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-catalog-root]');
            if (!root) return;

            const tabButtons = Array.from(root.querySelectorAll('[data-catalog-tab]'));
            const panels = {
                skus: root.querySelector('[data-catalog-panel="skus"]'),
                price_books: root.querySelector('[data-catalog-panel="price_books"]'),
            };

            const updateTabInputs = (tab) => {
                root.querySelectorAll('.catalog-active-tab-input').forEach((input) => {
                    input.value = tab;
                });
            };

            const setActiveTab = (tab, replaceUrl) => {
                if (!panels[tab]) return;
                tabButtons.forEach((button) => {
                    const isActive = button.getAttribute('data-catalog-tab') === tab;
                    button.classList.toggle('is-active', isActive);
                });
                Object.entries(panels).forEach(([key, panel]) => {
                    if (!panel) return;
                    panel.hidden = key !== tab;
                });
                updateTabInputs(tab);

                if (replaceUrl) {
                    const button = tabButtons.find((candidate) => candidate.getAttribute('data-catalog-tab') === tab);
                    const nextUrl = button ? button.getAttribute('data-catalog-tab-url') : null;
                    if (nextUrl && window.history && typeof window.history.replaceState === 'function') {
                        window.history.replaceState(null, '', nextUrl);
                    }
                }
            };

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (button.disabled) return;
                    const target = button.getAttribute('data-catalog-tab');
                    if (!target) return;
                    setActiveTab(target, true);
                });
            });

            const initialTab = root.getAttribute('data-active-tab') || 'skus';
            setActiveTab(initialTab, false);

            const backdrop = document.getElementById('catalog-detail-backdrop');
            const drawer = document.getElementById('catalog-detail-drawer');
            const closeButton = document.getElementById('catalog-detail-close');
            const titleEl = document.getElementById('catalog-detail-title');
            const subtitleEl = document.getElementById('catalog-detail-subtitle');
            const statusEl = document.getElementById('catalog-detail-status');
            const bodyEl = document.getElementById('catalog-detail-body');
            const linksEl = document.getElementById('catalog-detail-links');
            const deleteForm = document.getElementById('catalog-detail-delete-form');
            const deleteButton = document.getElementById('catalog-detail-delete-button');

            if (!backdrop || !drawer || !closeButton || !titleEl || !subtitleEl || !statusEl || !bodyEl || !linksEl || !deleteForm || !deleteButton) {
                return;
            }

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const closeDrawer = () => {
                drawer.hidden = true;
                backdrop.hidden = true;
            };

            const openDrawer = () => {
                drawer.hidden = false;
                backdrop.hidden = false;
            };

            root.querySelectorAll('.catalog-open-drawer').forEach((button) => {
                button.addEventListener('click', () => {
                    const raw = button.getAttribute('data-catalog-item') || '{}';
                    let payload = {};
                    try {
                        payload = JSON.parse(raw);
                    } catch (error) {
                        payload = {};
                    }

                    titleEl.textContent = payload.title || '詳細';
                    subtitleEl.textContent = payload.subtitle || '';

                    const statusText = payload.status_text || '';
                    const statusTone = payload.status_tone || 'normal';
                    statusEl.textContent = statusText;
                    statusEl.className = 'catalog-detail-status is-' + statusTone;

                    const details = Array.isArray(payload.details) ? payload.details : [];
                    bodyEl.innerHTML = details.map((row) => {
                        const label = escapeHtml(row.label || '');
                        const value = escapeHtml(row.value || '-');
                        return `<tr><th>${label}</th><td>${value}</td></tr>`;
                    }).join('');

                    linksEl.innerHTML = '';
                    const links = Array.isArray(payload.links) ? payload.links : [];
                    links.forEach((link) => {
                        const a = document.createElement('a');
                        a.href = String(link.url || '#');
                        a.textContent = String(link.label || 'リンク');
                        linksEl.appendChild(a);
                    });

                    const del = payload.delete;
                    if (del && del.url) {
                        deleteForm.hidden = false;
                        deleteForm.action = String(del.url);
                        deleteButton.textContent = String(del.label || '削除申請');
                        deleteButton.setAttribute('data-confirm-message', String(del.confirm || '削除申請を送信しますか？'));
                    } else {
                        deleteForm.hidden = true;
                        deleteForm.action = '';
                        deleteButton.textContent = '削除申請';
                        deleteButton.setAttribute('data-confirm-message', '削除申請を送信しますか？');
                    }

                    openDrawer();
                });
            });

            deleteForm.addEventListener('submit', (event) => {
                const message = deleteButton.getAttribute('data-confirm-message') || '削除申請を送信しますか？';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });

            closeButton.addEventListener('click', closeDrawer);
            backdrop.addEventListener('click', closeDrawer);
            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !drawer.hidden) {
                    closeDrawer();
                }
            });
        })();
    </script>
@endsection
