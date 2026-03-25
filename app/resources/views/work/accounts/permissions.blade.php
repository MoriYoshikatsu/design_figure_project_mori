@extends('work.layout')

@section('content')
    <h1>アカウント #{{ $account->id }} 変更申請必須設定</h1>

    <div class="actions" style="margin-bottom:12px;">
        <a href="{{ route('work.accounts.edit', $account->id) }}">アカウント詳細へ戻る</a>
        <a href="{{ route('work.accounts.index') }}">一覧へ戻る</a>
    </div>

    <div style="margin-bottom:12px; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#fff;">
        <div><strong>現在の設定:</strong> {{ $changeRequestRequirementSummary ?? '作成・更新はすべて必須' }}</div>
        <div class="muted" style="margin-top:6px;">
            チェックが入っている項目は、作成・更新で変更申請が必要です。チェックを外した項目は、作成・更新のみ即時反映されます。
            削除は設定に関係なく常に変更申請が必要です。この設定ページ自体の更新も、対象項目として設定できます。
        </div>
    </div>

    <form method="POST" action="{{ route('work.accounts.permissions.update', $account->id) }}" id="change-request-settings-form">
        @csrf
        <div class="actions" style="margin-bottom:8px;">
            <button type="button" id="settings-check-all">全項目の作成・更新を必須にする</button>
            <button type="button" id="settings-uncheck-all">全項目の作成・更新を即時反映にする</button>
            <span class="muted">{{ $changeRequestRequiredCount ?? 0 }} / {{ $changeRequestToggleableCount ?? 0 }} 項目の作成・更新が申請必須</span>
            <button type="submit">設定を保存</button>
        </div>

        <div style="border:1px solid #ddd; padding:8px; max-height:1000px; overflow:auto; margin-bottom:8px;">
            @forelse(($changeRequestRequirementGroups ?? []) as $group)
                @php
                    $groupKey = (string)($group['group_key'] ?? 'other');
                    $groupLabel = (string)($group['group_label'] ?? 'その他');
                    $groupItems = is_array($group['items'] ?? null) ? $group['items'] : [];
                @endphp
                <section style="border:1px solid #e5e7eb; border-radius:8px; margin-bottom:12px;">
                    <header style="display:flex; align-items:center; gap:8px; padding:8px 10px; background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" class="setting-group-master" data-group="{{ $groupKey }}">
                            <strong>{{ $groupLabel }}</strong>
                        </label>
                        <span class="muted">({{ count($groupItems) }}件)</span>
                    </header>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:72px;">必須</th>
                                <th style="width:220px;">対象項目</th>
                                <th>対象操作（削除は常に必須）</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groupItems as $item)
                                @php
                                    $entityType = (string)($item['entity_type'] ?? '');
                                    $checked = (bool)($changeRequestRequirementStateMap[$entityType] ?? true);
                                @endphp
                                <tr>
                                    <td style="text-align:center;">
                                        <input
                                            type="checkbox"
                                            name="required_entity_types[]"
                                            value="{{ $entityType }}"
                                            class="setting-item-checkbox"
                                            data-group="{{ $groupKey }}"
                                            @checked($checked)
                                        >
                                    </td>
                                    <td>
                                        <strong>{{ $item['label'] ?? $entityType }}</strong>
                                    </td>
                                    <td>{{ $item['description'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">このグループの対象項目はありません。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @empty
                <div>変更申請必須設定の対象項目が見つかりません。</div>
            @endforelse
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const groupMasters = Array.from(document.querySelectorAll('.setting-group-master'));
            const itemBoxes = Array.from(document.querySelectorAll('.setting-item-checkbox'));
            const checkAllBtn = document.getElementById('settings-check-all');
            const uncheckAllBtn = document.getElementById('settings-uncheck-all');

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
