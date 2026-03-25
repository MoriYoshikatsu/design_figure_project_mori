<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountChangeRequestRequirementService
{
    /** @var array<int, array{entity_type:string,label:string,short_label:string,description:string,group_key:string,group_label:string,group_order:int,sort_order:int}> */
    private const CATALOG = [
        [
            'entity_type' => 'account',
            'label' => 'アカウント',
            'short_label' => 'アカウント',
            'description' => 'アカウントの作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'account',
            'group_label' => 'アカウント',
            'group_order' => 10,
            'sort_order' => 10,
        ],
        [
            'entity_type' => 'account_user_memo',
            'label' => 'アカウントメンバーメモ',
            'short_label' => 'メンバーメモ',
            'description' => 'アカウント詳細で編集するメンバー別メモの更新に変更申請を要求します。',
            'group_key' => 'account',
            'group_label' => 'アカウント',
            'group_order' => 10,
            'sort_order' => 20,
        ],
        [
            'entity_type' => 'account_change_request_requirement',
            'label' => '変更申請必須設定',
            'short_label' => '申請設定',
            'description' => 'このページで更新する変更申請必須設定の変更に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'account',
            'group_label' => 'アカウント',
            'group_order' => 10,
            'sort_order' => 30,
        ],
        [
            'entity_type' => 'quote',
            'label' => '見積・仕様書',
            'short_label' => '見積',
            'description' => 'Configurator と見積編集から行う見積更新・見積メモ更新に変更申請を要求します。',
            'group_key' => 'quote',
            'group_label' => '見積・仕様書',
            'group_order' => 20,
            'sort_order' => 10,
        ],
        [
            'entity_type' => 'part',
            'label' => 'Part',
            'short_label' => 'Part',
            'description' => 'Part マスタの作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'catalog',
            'group_label' => 'Parts & Pricing',
            'group_order' => 30,
            'sort_order' => 10,
        ],
        [
            'entity_type' => 'price_book',
            'label' => '価格表',
            'short_label' => '価格表',
            'description' => '価格表本体の作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'catalog',
            'group_label' => 'Parts & Pricing',
            'group_order' => 30,
            'sort_order' => 20,
        ],
        [
            'entity_type' => 'price_book_item',
            'label' => '価格表明細',
            'short_label' => '価格表明細',
            'description' => '価格表明細の作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'catalog',
            'group_label' => 'Parts & Pricing',
            'group_order' => 30,
            'sort_order' => 30,
        ],
        [
            'entity_type' => 'product_template',
            'label' => 'テンプレート',
            'short_label' => 'テンプレート',
            'description' => '納品規則テンプレートの作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'template',
            'group_label' => 'テンプレート',
            'group_order' => 40,
            'sort_order' => 10,
        ],
        [
            'entity_type' => 'product_template_version',
            'label' => 'テンプレート版',
            'short_label' => 'テンプレ版',
            'description' => 'テンプレート版の作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'template',
            'group_label' => 'テンプレート',
            'group_order' => 40,
            'sort_order' => 20,
        ],
        [
            'entity_type' => 'labor_cost_setting',
            'label' => '作業費全体設定',
            'short_label' => '作業費設定',
            'description' => '作業費管理の全体変数更新に変更申請を要求します。',
            'group_key' => 'labor',
            'group_label' => '作業費管理',
            'group_order' => 50,
            'sort_order' => 10,
        ],
        [
            'entity_type' => 'labor_process',
            'label' => '加工工程',
            'short_label' => '加工工程',
            'description' => '加工工程の作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'labor',
            'group_label' => '作業費管理',
            'group_order' => 50,
            'sort_order' => 20,
        ],
        [
            'entity_type' => 'labor_process_element',
            'label' => '工程要素',
            'short_label' => '工程要素',
            'description' => '工程要素の作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'labor',
            'group_label' => '作業費管理',
            'group_order' => 50,
            'sort_order' => 30,
        ],
        [
            'entity_type' => 'labor_auto_rule',
            'label' => '自動適用ルール',
            'short_label' => '自動ルール',
            'description' => '自動適用ルールの作成・更新に変更申請を要求します。削除は常に変更申請が必要です。',
            'group_key' => 'labor',
            'group_label' => '作業費管理',
            'group_order' => 50,
            'sort_order' => 40,
        ],
    ];

    /**
     * @return array<int, array{entity_type:string,label:string,short_label:string,description:string,group_key:string,group_label:string,group_order:int,sort_order:int}>
     */
    public function catalog(): array
    {
        return self::CATALOG;
    }

    /**
     * @return array<int, string>
     */
    public function toggleableEntityTypes(): array
    {
        return array_column(self::CATALOG, 'entity_type');
    }

    public function storageAvailable(): bool
    {
        return $this->storageReady();
    }

    /**
     * @param array<int, mixed> $requiredEntityTypes
     * @return array<int, string>
     */
    public function normalizeRequiredEntityTypes(array $requiredEntityTypes): array
    {
        $selected = [];
        $selectedMap = [];
        foreach ($requiredEntityTypes as $entityType) {
            $normalized = $this->normalizeEntityType((string)$entityType);
            if ($normalized === '' || isset($selectedMap[$normalized])) {
                continue;
            }
            $selectedMap[$normalized] = true;
        }

        foreach ($this->toggleableEntityTypes() as $entityType) {
            if (isset($selectedMap[$entityType])) {
                $selected[] = $entityType;
            }
        }

        return $selected;
    }

    /**
     * @return array<int, string>
     */
    public function selectedRequiredEntityTypes(int $accountId): array
    {
        $stateMap = $this->stateMap($accountId);
        $selected = [];
        foreach ($this->toggleableEntityTypes() as $entityType) {
            if (($stateMap[$entityType] ?? true) === true) {
                $selected[] = $entityType;
            }
        }

        return $selected;
    }

    /**
     * @return array<int, array{group_key:string,group_label:string,items:array<int, array{entity_type:string,label:string,short_label:string,description:string,group_key:string,group_label:string,group_order:int,sort_order:int}>}>
     */
    public function catalogGroups(): array
    {
        $groups = [];
        foreach (self::CATALOG as $item) {
            $groupKey = $item['group_key'];
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group_key' => $groupKey,
                    'group_label' => $item['group_label'],
                    'group_order' => $item['group_order'],
                    'items' => [],
                ];
            }
            $groups[$groupKey]['items'][] = $item;
        }

        uasort($groups, static fn (array $a, array $b): int => ((int)$a['group_order']) <=> ((int)$b['group_order']));

        return array_values(array_map(static function (array $group): array {
            usort($group['items'], static function (array $a, array $b): int {
                $sortCmp = ((int)$a['sort_order']) <=> ((int)$b['sort_order']);
                if ($sortCmp !== 0) {
                    return $sortCmp;
                }

                return strcmp((string)$a['label'], (string)$b['label']);
            });

            unset($group['group_order']);
            return $group;
        }, $groups));
    }

    /**
     * @return array<string, bool>
     */
    public function stateMap(int $accountId): array
    {
        $stateMap = [];
        foreach ($this->toggleableEntityTypes() as $entityType) {
            $stateMap[$entityType] = true;
        }

        if ($accountId <= 0 || !$this->storageReady()) {
            return $stateMap;
        }

        $rows = DB::table('account_change_request_requirements')
            ->where('account_id', $accountId)
            ->whereIn('entity_type', array_unique(array_merge(array_keys($stateMap), ['sku'])))
            ->get(['entity_type', 'is_required']);

        foreach ($rows as $row) {
            $entityType = $this->normalizeEntityType((string)$row->entity_type);
            if (array_key_exists($entityType, $stateMap)) {
                $stateMap[$entityType] = (bool)$row->is_required;
            }
        }

        return $stateMap;
    }

    /**
     * @param array<int, int> $accountIds
     * @return array<int, string>
     */
    public function summaryMap(array $accountIds): array
    {
        $accountIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => (int)$value,
            $accountIds
        ), static fn (int $value): bool => $value > 0)));

        if (empty($accountIds)) {
            return [];
        }

        $stateRowsByAccount = [];
        if ($this->storageReady()) {
            $rows = DB::table('account_change_request_requirements')
                ->whereIn('account_id', $accountIds)
                ->whereIn('entity_type', array_unique(array_merge($this->toggleableEntityTypes(), ['sku'])))
                ->get(['account_id', 'entity_type', 'is_required']);

            foreach ($rows as $row) {
                $accountId = (int)$row->account_id;
                $entityType = $this->normalizeEntityType((string)$row->entity_type);
                $stateRowsByAccount[$accountId][$entityType] = (bool)$row->is_required;
            }
        }

        $summaryMap = [];
        foreach ($accountIds as $accountId) {
            $stateMap = [];
            foreach ($this->toggleableEntityTypes() as $entityType) {
                $stateMap[$entityType] = $stateRowsByAccount[$accountId][$entityType] ?? true;
            }
            $summaryMap[$accountId] = $this->buildSummaryFromStateMap($stateMap);
        }

        return $summaryMap;
    }

    public function sync(int $accountId, array $requiredEntityTypes, int $updatedBy = 0): void
    {
        if ($accountId <= 0 || !$this->storageReady()) {
            throw new \RuntimeException('account_change_request_requirements storage is not ready.');
        }

        $stateMap = [];
        foreach ($this->toggleableEntityTypes() as $entityType) {
            $stateMap[$entityType] = false;
        }

        foreach ($this->normalizeRequiredEntityTypes($requiredEntityTypes) as $entityType) {
            if (array_key_exists($entityType, $stateMap)) {
                $stateMap[$entityType] = true;
            }
        }

        DB::transaction(function () use ($accountId, $stateMap, $updatedBy): void {
            foreach ($stateMap as $entityType => $isRequired) {
                DB::table('account_change_request_requirements')->updateOrInsert(
                    [
                        'account_id' => $accountId,
                        'entity_type' => $entityType,
                    ],
                    [
                        'is_required' => $isRequired,
                        'updated_by' => $updatedBy > 0 ? $updatedBy : null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    public function requiresChangeRequest(
        string $entityType,
        int $entityId,
        int $requestedBy,
        mixed $before,
        mixed $after,
        array $meta = [],
        string $operation = 'UPDATE'
    ): bool {
        if (strtoupper(trim($operation)) === 'DELETE') {
            return true;
        }

        $entityType = $this->normalizeEntityType($entityType);
        if (!in_array($entityType, $this->toggleableEntityTypes(), true)) {
            return true;
        }

        $accountId = $this->resolveAccountId($entityType, $entityId, $requestedBy, $before, $after, $meta);
        if ($accountId <= 0) {
            return true;
        }

        return (bool)($this->stateMap($accountId)[$entityType] ?? true);
    }

    /**
     * @return array{
     *   changeRequestRequirementGroups: array<int, array{group_key:string,group_label:string,items:array<int, array{entity_type:string,label:string,short_label:string,description:string,group_key:string,group_label:string,group_order:int,sort_order:int}>}>,
     *   changeRequestRequirementStateMap: array<string, bool>,
     *   changeRequestRequiredCount: int,
     *   changeRequestToggleableCount: int,
     *   changeRequestRequirementSummary: string
     * }
     */
    public function buildViewData(int $accountId): array
    {
        $stateMap = $this->stateMap($accountId);

        return [
            'changeRequestRequirementGroups' => $this->catalogGroups(),
            'changeRequestRequirementStateMap' => $stateMap,
            'changeRequestRequiredCount' => count(array_filter($stateMap, static fn (bool $isRequired): bool => $isRequired)),
            'changeRequestToggleableCount' => count($stateMap),
            'changeRequestRequirementSummary' => $this->buildSummaryFromStateMap($stateMap),
        ];
    }

    /**
     * @param array<string, bool> $stateMap
     */
    private function buildSummaryFromStateMap(array $stateMap): string
    {
        $requiredLabels = [];
        foreach (self::CATALOG as $item) {
            $entityType = $item['entity_type'];
            if (($stateMap[$entityType] ?? true) === true) {
                $requiredLabels[] = $item['short_label'];
            }
        }

        $requiredCount = count($requiredLabels);
        $totalCount = count(self::CATALOG);

        if ($requiredCount === $totalCount) {
            return '作成・更新はすべて必須';
        }
        if ($requiredCount === 0) {
            return '作成・更新は即時反映';
        }
        if ($requiredCount <= 3) {
            return '作成・更新: ' . implode(' / ', $requiredLabels);
        }

        return '作成・更新: ' . implode(' / ', array_slice($requiredLabels, 0, 3)) . ' 他' . ($requiredCount - 3) . '件';
    }

    private function resolveAccountId(
        string $entityType,
        int $entityId,
        int $requestedBy,
        mixed $before,
        mixed $after,
        array $meta
    ): int {
        $metaAccountId = (int)($meta['account_id'] ?? 0);
        if ($metaAccountId > 0) {
            return $metaAccountId;
        }

        foreach ([$after, $before] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $accountId = (int)($candidate['account_id'] ?? 0);
            if ($accountId > 0) {
                return $accountId;
            }
        }

        if ($entityType === 'account' && $entityId > 0) {
            return $entityId;
        }

        if ($entityType === 'account_change_request_requirement' && $entityId > 0) {
            return $entityId;
        }

        if ($entityType === 'quote' && $entityId > 0) {
            $accountId = (int)DB::table('quotes')
                ->whereNull('deleted_at')
                ->where('id', $entityId)
                ->value('account_id');
            if ($accountId > 0) {
                return $accountId;
            }
        }

        return $this->resolvePrimaryAccountIdForUser($requestedBy);
    }

    private function resolvePrimaryAccountIdForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int)DB::table('account_user')
            ->where('user_id', $userId)
            ->orderByRaw("
                case role
                    when 'sales' then 1
                    when 'admin' then 2
                    when 'customer' then 3
                    else 9
                end
            ")
            ->orderBy('account_id')
            ->value('account_id');
    }

    private function normalizeEntityType(string $entityType): string
    {
        $entityType = strtolower(trim($entityType));
        return $entityType === 'sku' ? 'part' : $entityType;
    }

    private function storageReady(): bool
    {
        if (Schema::hasTable('account_change_request_requirements')) {
            return true;
        }

        try {
            Schema::create('account_change_request_requirements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->string('entity_type', 100);
                $table->boolean('is_required')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['account_id', 'entity_type'], 'acct_change_req_unique');
                $table->index('entity_type', 'acct_change_req_entity_idx');
            });
        } catch (\Throwable $e) {
            // Another process may have created the table, or the DB user may lack schema permissions.
        }

        return Schema::hasTable('account_change_request_requirements');
    }
}
