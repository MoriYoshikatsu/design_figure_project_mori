<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '受注販売管理システム' }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; }
        .layout-root { display: flex; min-height: 100vh; background: #f9fafb; }
        .sidebar {
            width: 64px;
            flex: 0 0 64px;
            border-right: 1px solid #e5e7eb;
            background: #fff;
            padding: 10px 8px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            position: sticky;
            top: 0;
            height: 100vh;
            box-sizing: border-box;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar-item {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #fff;
            color: #374151;
            text-decoration: none;
            box-sizing: border-box;
        }
        .sidebar-item:hover {
            background: #f3f4f6;
            color: #111827;
            border-color: #9ca3af;
        }
        .sidebar-item.is-active {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #93c5fd;
        }
        .sidebar-item svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .sidebar-item[data-label]::after,
        .sidebar-trigger[data-label]::after {
            content: attr(data-label);
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            background: #111827;
            color: #fff;
            font-size: 12px;
            line-height: 1;
            white-space: nowrap;
            padding: 7px 9px;
            border-radius: 6px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.12s ease;
            z-index: 40;
        }
        .sidebar-item:hover::after,
        .sidebar-trigger:hover::after {
            opacity: 1;
        }
        .account-menu {
            margin-top: auto;
            position: relative;
        }
        .sidebar-trigger {
            list-style: none;
            cursor: pointer;
        }
        .sidebar-trigger::-webkit-details-marker { display: none; }
        .account-dropdown {
            position: absolute;
            left: calc(100% + 10px);
            bottom: 0;
            min-width: 250px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            padding: 8px;
            z-index: 30;
        }
        .account-meta {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 8px 8px;
            margin-bottom: 6px;
        }
        .account-meta strong { display: block; }
        .account-meta .muted { font-size: 12px; word-break: break-all; }
        .account-actions a,
        .account-actions button {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            color: #111827;
            text-decoration: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            font: inherit;
        }
        .account-actions a:hover,
        .account-actions button:hover { background: #f3f4f6; }
        .app-shell { flex: 1; min-width: 0; padding: 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea { width: 100%; }
        textarea { min-height: 140px; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; }
        .col { flex: 1; }
        .muted { color: #6b7280; }
        .actions { display: flex; gap: 8px; align-items: center; }
        @media (max-width: 900px) {
            .sidebar {
                width: 56px;
                flex-basis: 56px;
                padding: 8px 6px;
            }
            .sidebar-item {
                width: 42px;
                height: 42px;
            }
            .app-shell { padding: 12px; }
        }
    </style>
</head>
<body>
    <div class="layout-root">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <a href="{{ route('admin.accounts.index') }}" class="sidebar-item @if(request()->routeIs('admin.accounts.*')) is-active @endif" data-label="アカウント">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.8-3.2 3.6-5 7.5-5s6.7 1.8 7.5 5"/></svg>
                </a>
                <a href="{{ route('ops.sessions.index') }}" class="sidebar-item @if(request()->routeIs('ops.sessions.*')) is-active @endif" data-label="仕様書セッション">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                </a>
                <a href="{{ route('ops.quotes.index') }}" class="sidebar-item @if(request()->routeIs('ops.quotes.*')) is-active @endif" data-label="仕様書見積">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12v16H6z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                </a>
                <a href="{{ route('admin.skus.index') }}" class="sidebar-item @if(request()->routeIs('admin.skus.*')) is-active @endif" data-label="パーツ(SKU)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5 12 5l8 4.5-8 4.5-8-4.5Z"/><path d="M4 9.5V15l8 4 8-4V9.5"/></svg>
                </a>
                <a href="{{ route('admin.price-books.index') }}" class="sidebar-item @if(request()->routeIs('admin.price-books.*')) is-active @endif" data-label="パーツ価格表">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="4.5" width="15" height="15" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                </a>
                <a href="{{ route('admin.templates.index') }}" class="sidebar-item @if(request()->routeIs('admin.templates.*')) is-active @endif" data-label="納品規則テンプレ(DSL)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6 4 12l4 6M16 6l4 6-4 6M13.5 4l-3 16"/></svg>
                </a>
                <a href="{{ route('admin.change-requests.index') }}" class="sidebar-item @if(request()->routeIs('admin.change-requests.*')) is-active @endif" data-label="編集承認リクエスト">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 6.5h15v11h-15z"/><path d="M8 10.5h8M8 14.5h5"/><path d="m15.5 4 1.5 2"/></svg>
                </a>
                <a href="{{ route('admin.audit-logs.index') }}" class="sidebar-item @if(request()->routeIs('admin.audit-logs.*')) is-active @endif" data-label="全作業監査ログ">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v7l4 2"/><circle cx="12" cy="12" r="8"/></svg>
                </a>
            </nav>

            @auth
                @php $currentUser = auth()->user(); @endphp
                <details class="account-menu">
                    <summary class="sidebar-item sidebar-trigger" data-label="{{ $currentUser?->name ?? 'ユーザーメニュー' }}" aria-label="ユーザーメニューを開く">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <div class="account-dropdown">
                        <div class="account-meta">
                            <strong>{{ $currentUser?->name ?? 'ユーザー' }}</strong>
                            <div class="muted">{{ $currentUser?->email ?? '' }}</div>
                        </div>
                        <div class="account-actions">
                            <a href="{{ route('user.settings') }}">ユーザー設定</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">ログアウト</button>
                            </form>
                        </div>
                    </div>
                </details>
            @endauth
        </aside>

        <main class="app-shell">
        @php
            $statusMessage = session('status');
            if ($statusMessage === 'profile-information-updated') {
                $statusMessage = 'ユーザー情報を更新しました。';
            } elseif ($statusMessage === 'password-updated') {
                $statusMessage = 'パスワードを更新しました。';
            }
        @endphp
        @if($statusMessage)
            <div style="margin:12px 0; padding:8px; background:#ecfeff; border:1px solid #06b6d4;">
                {{ $statusMessage }}
            </div>
        @endif

        @php
            $errorsBag = $errors ?? null;
            $defaultErrors = null;
            if ($errorsBag instanceof \Illuminate\Support\ViewErrorBag) {
                $defaultErrors = $errorsBag->getBag('default');
            } elseif ($errorsBag instanceof \Illuminate\Support\MessageBag) {
                $defaultErrors = $errorsBag;
            }
        @endphp
        @if($defaultErrors instanceof \Illuminate\Support\MessageBag && $defaultErrors->any())
            <div style="margin:12px 0; padding:8px; background:#fee2e2; border:1px solid #dc2626;">
                <ul>
                    @foreach($defaultErrors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
        </main>
    </div>
</body>
</html>
