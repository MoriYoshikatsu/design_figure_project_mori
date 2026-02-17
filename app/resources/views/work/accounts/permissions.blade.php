@extends('work.layout')

@section('content')
    <h1>アカウント #{{ $account->id }} ルート権限編集</h1>

    <div class="actions" style="margin-bottom:12px;">
        <a href="{{ route('work.accounts.edit', $account->id) }}">アカウント詳細へ戻る</a>
        <a href="{{ route('work.accounts.index') }}">一覧へ戻る</a>
    </div>
    @php
        $catalogGroups = $salesRouteCatalogGroups ?? [];
        if (empty($catalogGroups)) {
            $catalogGroups = [[
                'group_key' => 'all',
                'group_label' => '全ルート',
                'items' => $salesRouteCatalog ?? [],
            ]];
        }
    @endphp

    {{-- <h3>チェックボックス一括設定（認証系は除外）</h3> --}}
    <form method="POST" action="{{ route('work.accounts.sales-route-permissions.edit-request.create', $account->id) }}" id="catalog-permission-form">
        @csrf
        <input type="hidden" name="_mode" value="submit">
        <input type="hidden" name="catalog_sync" value="1">
        <div class="actions" style="margin-bottom:8px;">
            <button type="button" id="catalog-check-all">全グループをチェック</button>
            <button type="button" id="catalog-uncheck-all">全グループを解除</button>
            <button type="submit">チェック内容反映を申請</button>
        </div>
        <div style="border:1px solid #ddd; padding:8px; max-height:1000px; overflow:auto; margin-bottom:8px;">
            @forelse($catalogGroups as $group)
                @php
                    $groupKey = (string)($group['group_key'] ?? 'other');
                    $groupLabel = (string)($group['group_label'] ?? 'その他');
                    $groupItems = is_array($group['items'] ?? null) ? $group['items'] : [];
                @endphp
                <section style="border:1px solid #e5e7eb; border-radius:8px; margin-bottom:12px;">
                    <header style="display:flex; align-items:center; gap:8px; padding:8px 10px; background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" class="catalog-group-master" data-group="{{ $groupKey }}">
                            <strong>{{ $groupLabel }}</strong>
                        </label>
                        <span class="muted">({{ count($groupItems) }}件)</span>
                    </header>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:64px;">No.</th>
                                <th style="width:60px;">許可</th>
                                <th style="width:76px;">Method</th>
                                <th>URI</th>
                                <th>推奨パターン</th>
                                <th>メモ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groupItems as $r)
                                @php
                                    $key = (string)$r['method'] . ' ' . (string)$r['suggested_pattern'];
                                    $checked = (bool)($salesRoutePermissionStateMap[$key] ?? false);
                                    $catalogNo = (string)($r['catalog_number'] ?? str_pad((string)(($loop->index ?? 0) + 1), 3, '0', STR_PAD_LEFT));
                                @endphp
                                <tr>
                                    <td><code>{{ $catalogNo }}</code></td>
                                    <td style="text-align:center;">
                                        <input
                                            type="checkbox"
                                            name="catalog_permissions[]"
                                            value="{{ $r['method'] }} {{ $r['suggested_pattern'] }}"
                                            class="catalog-permission-checkbox"
                                            data-group="{{ $groupKey }}"
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
                                    <td colspan="6">このグループの対象ルートはありません。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @empty
                <div>対象ルートが見つかりません。</div>
            @endforelse
        </div>
    </form>

    <h1 style="margin-top:16px;">新規ルート追加</h1>
    <form method="POST" action="{{ route('work.accounts.sales-route-permissions.edit-request.create', $account->id) }}">
        @csrf
        <input type="hidden" name="_mode" value="submit">
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
                        <input type="text" name="uri_pattern" placeholder="/work/quotes/*">
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
        <button type="submit">手入力ルール追加を申請</button>
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
                        <form method="POST" action="{{ route('work.accounts.sales-route-permissions.edit-request.update', [$account->id, $perm->id]) }}">
                            @csrf
                            <input type="hidden" name="_mode" value="submit">
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
                        <form method="POST" action="{{ route('work.accounts.sales-route-permissions.edit-request.delete', [$account->id, $perm->id]) }}">
                            @csrf
                            <input type="hidden" name="_mode" value="submit">
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const groupMasters = Array.from(document.querySelectorAll('.catalog-group-master'));
            const itemBoxes = Array.from(document.querySelectorAll('.catalog-permission-checkbox'));
            const checkAllBtn = document.getElementById('catalog-check-all');
            const uncheckAllBtn = document.getElementById('catalog-uncheck-all');

            const itemsByGroup = (groupKey) => itemBoxes.filter((el) => (el.dataset.group || '') === groupKey);

            const syncGroupMasterState = (groupKey) => {
                const master = groupMasters.find((el) => (el.dataset.group || '') === groupKey);
                if (!master) {
                    return;
                }
                const items = itemsByGroup(groupKey);
                if (items.length === 0) {
                    master.checked = false;
                    master.indeterminate = false;
                    return;
                }

                const checkedCount = items.filter((el) => el.checked).length;
                master.checked = checkedCount === items.length;
                master.indeterminate = checkedCount > 0 && checkedCount < items.length;
            };

            groupMasters.forEach((master) => {
                const groupKey = master.dataset.group || '';
                master.addEventListener('change', () => {
                    const checked = master.checked;
                    itemsByGroup(groupKey).forEach((item) => {
                        item.checked = checked;
                    });
                    syncGroupMasterState(groupKey);
                });
                syncGroupMasterState(groupKey);
            });

            itemBoxes.forEach((item) => {
                item.addEventListener('change', () => {
                    syncGroupMasterState(item.dataset.group || '');
                });
            });

            const setAll = (checked) => {
                itemBoxes.forEach((item) => {
                    item.checked = checked;
                });
                groupMasters.forEach((master) => {
                    syncGroupMasterState(master.dataset.group || '');
                });
            };

            if (checkAllBtn) {
                checkAllBtn.addEventListener('click', () => setAll(true));
            }
            if (uncheckAllBtn) {
                uncheckAllBtn.addEventListener('click', () => setAll(false));
            }
        });
    </script>
@endsection
