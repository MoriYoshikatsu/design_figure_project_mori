@extends('admin.layout')

@section('content')
    <h1>アカウント #{{ $account->id }} ルート権限設定</h1>

    <div class="actions" style="margin-bottom:12px;">
        <a href="{{ route('admin.accounts.edit', $account->id) }}">アカウント詳細へ戻る</a>
        <a href="{{ route('admin.accounts.index') }}">一覧へ戻る</a>
    </div>

    <form method="POST" action="{{ route('admin.accounts.sales-route-policy.update', $account->id) }}" style="margin-bottom:12px;">
        @csrf
        @method('PUT')
        <table>
            <tbody>
                <tr>
                    <th>ポリシーモード</th>
                    <td>
                        <select name="sales_route_policy_mode">
                            <option value="legacy_allow_all" @selected(($account->sales_route_policy_mode ?? 'legacy_allow_all') === 'legacy_allow_all')>既存維持（legacy_allow_all）</option>
                            <option value="strict_allowlist" @selected(($account->sales_route_policy_mode ?? 'legacy_allow_all') === 'strict_allowlist')>厳格許可制（strict_allowlist）</option>
                        </select>
                        <div class="muted">厳格許可制では、許可一覧に一致する Method + URI パターンのみ Sales が通過します。</div>
                    </td>
                    <td style="width:140px;">
                        <button type="submit">モード保存</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>

    <h3>チェックボックス一括設定（認証系は除外）</h3>
    <form method="POST" action="{{ route('admin.accounts.sales-route-permissions.store', $account->id) }}">
        @csrf
        <input type="hidden" name="catalog_sync" value="1">
        <div style="border:1px solid #ddd; padding:8px; max-height:1000
        px; overflow:auto; margin-bottom:8px;">
            <table>
                <thead>
                    <tr>
                        <th>許可</th>
                        <th>Method</th>
                        <th>URI</th>
                        <th>推奨パターン</th>
                        <th>メモ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesRouteCatalog as $r)
                        @php
                            $key = $r['method'] . ' ' . $r['suggested_pattern'];
                            $checked = (bool)($salesRoutePermissionStateMap[$key] ?? false);
                        @endphp
                        <tr>
                            <td style="width:60px; text-align:center;">
                                <input
                                    type="checkbox"
                                    name="catalog_permissions[]"
                                    value="{{ $r['method'] }} {{ $r['suggested_pattern'] }}"
                                    @checked($checked)
                                >
                            </td>
                            <td>{{ $r['method'] }}</td>
                            <td><code>{{ $r['uri'] }}</code></td>
                            <td><code>{{ $r['suggested_pattern'] }}</code></td>
                            <td>{{ $r['memo'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">対象ルートが見つかりません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <button type="submit">チェック内容を反映</button>
    </form>

    <h3 style="margin-top:16px;">手入力で追加</h3>
    <form method="POST" action="{{ route('admin.accounts.sales-route-permissions.store', $account->id) }}">
        @csrf
        <table>
            <tbody>
                <tr>
                    <th>HTTPメソッド</th>
                    <td>
                        <select name="http_method">
                            @foreach($salesRouteAllowedMethods as $m)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endforeach
                        </select>
                    </td>
                    <th>URIパターン</th>
                    <td>
                        <input type="text" name="uri_pattern" placeholder="/ops/quotes/*">
                    </td>
                </tr>
                <tr>
                    <th>メモ</th>
                    <td colspan="3">
                        <textarea name="memo" rows="2" style="width:100%;"></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <input type="hidden" name="source" value="manual">
        <button type="submit">手入力ルールを追加</button>
    </form>
{{-- 
    <h3 style="margin-top:16px;">登録済みルール一覧</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Method</th>
                <th>URIパターン</th>
                <th>source</th>
                <th>active</th>
                <th>メモ</th>
                <th>更新</th>
                <th>削除</th>
            </tr>
        </thead>
        <tbody>
            @forelse($salesRoutePermissions as $perm)
                <tr>
                    <td>{{ $perm->id }}</td>
                    <td>{{ $perm->http_method }}</td>
                    <td><code>{{ $perm->uri_pattern }}</code></td>
                    <td>{{ $perm->source }}</td>
                    <td>{{ (bool)$perm->active ? 'true' : 'false' }}</td>
                    <td>{{ $perm->memo ?: '-' }}</td>
                    <td style="width:360px;">
                        <form method="POST" action="{{ route('admin.accounts.sales-route-permissions.update', [$account->id, $perm->id]) }}">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col" style="flex:0 0 95px;">
                                    <select name="http_method">
                                        @foreach($salesRouteAllowedMethods as $m)
                                            <option value="{{ $m }}" @selected($m === $perm->http_method)>{{ $m }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col">
                                    <input type="text" name="uri_pattern" value="{{ $perm->uri_pattern }}">
                                </div>
                            </div>
                            <div class="row" style="margin-top:6px;">
                                <div class="col" style="flex:0 0 110px;">
                                    <select name="source">
                                        <option value="checkbox" @selected($perm->source === 'checkbox')>checkbox</option>
                                        <option value="manual" @selected($perm->source === 'manual')>manual</option>
                                    </select>
                                </div>
                                <div class="col" style="flex:0 0 80px;">
                                    <label><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked((bool)$perm->active)> active</label>
                                </div>
                                <div class="col" style="flex:0 0 80px;">
                                    <button type="submit">保存</button>
                                </div>
                            </div>
                            <div style="margin-top:6px;">
                                <textarea name="memo" rows="2" style="width:100%;">{{ $perm->memo }}</textarea>
                            </div>
                        </form>
                    </td>
                    <td style="width:90px;">
                        <form method="POST" action="{{ route('admin.accounts.sales-route-permissions.destroy', [$account->id, $perm->id]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('このルールを削除しますか？')">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Salesルート許可は未登録です。</td>
                </tr>
            @endforelse
        </tbody>
    </table> --}}
@endsection
