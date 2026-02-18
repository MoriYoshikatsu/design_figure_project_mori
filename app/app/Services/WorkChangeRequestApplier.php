<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class WorkChangeRequestApplier
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function apply(object $requestRow, array $payload, int $actorId): int
    {
        $entityType = (string)$requestRow->entity_type;
        $entityId = (int)$requestRow->entity_id;
        $requestedBy = (int)($requestRow->requested_by ?? 0);
        $operation = strtoupper((string)($requestRow->operation ?? 'UPDATE'));
        $before = $payload['before'] ?? null;
        $after = $payload['after'] ?? null;

        return match ($operation) {
            'CREATE' => $this->applyCreate($entityType, is_array($after) ? $after : [], $actorId, $requestedBy),
            'DELETE' => $this->applyDelete($entityType, $entityId, $actorId, $before),
            default => $this->applyUpdate($entityType, $entityId, $before, $after, $actorId),
        };
    }

    private function applyCreate(string $entityType, array $after, int $actorId, int $requestedBy): int
    {
        if ($entityType === 'account') {
            $role = in_array((string)($after['role'] ?? ''), ['admin', 'sales', 'customer'], true)
                ? (string)$after['role']
                : 'customer';
            $accountType = (string)($after['account_type'] ?? '');
            if ($accountType === '') {
                $accountType = $role === 'customer' ? 'B2C' : 'B2B';
            }

            $id = (int)DB::table('accounts')->insertGetId([
                'account_type' => $accountType,
                'internal_name' => $after['internal_name'] ?? null,
                'memo' => $after['memo'] ?? null,
                'assignee_name' => $after['assignee_name'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $linkedUserId = 0;
            $userName = trim((string)($after['user_name'] ?? ''));
            $userEmail = strtolower(trim((string)($after['user_email'] ?? '')));
            $userPasswordHash = (string)($after['user_password_hash'] ?? '');

            if ($userName !== '' && $userEmail !== '' && $userPasswordHash !== '') {
                $existingUserId = (int)DB::table('users')
                    ->whereRaw('lower(email) = ?', [$userEmail])
                    ->value('id');

                if ($existingUserId > 0) {
                    $linkedUserId = $existingUserId;
                } else {
                    if (
                        !str_starts_with($userPasswordHash, '$2y$')
                        && !str_starts_with($userPasswordHash, '$argon2i$')
                        && !str_starts_with($userPasswordHash, '$argon2id$')
                    ) {
                        $userPasswordHash = Hash::make($userPasswordHash);
                    }

                    $linkedUserId = (int)DB::table('users')->insertGetId([
                        'name' => $userName,
                        'email' => $userEmail,
                        'password' => $userPasswordHash,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($linkedUserId <= 0 && $requestedBy > 0 && DB::table('users')->where('id', $requestedBy)->exists()) {
                $linkedUserId = $requestedBy;
            }

            if ($linkedUserId > 0) {
                DB::table('account_user')->updateOrInsert(
                    ['account_id' => $id, 'user_id' => $linkedUserId],
                    [
                        'role' => $role,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $auditAfter = $after;
            unset($auditAfter['user_password_hash']);
            $this->auditLogger->log($actorId, 'ACCOUNT_CREATED', 'account', $id, null, $auditAfter);
            return $id;
        }

        if ($entityType === 'sku') {
            $id = (int)DB::table('skus')->insertGetId([
                'sku_code' => $after['sku_code'] ?? '',
                'name' => $after['name'] ?? '',
                'category' => $after['category'] ?? 'PROC',
                'active' => (bool)($after['active'] ?? true),
                'attributes' => json_encode($after['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                'memo' => $after['memo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'SKU_CREATED', 'sku', $id, null, $after);
            return $id;
        }

        if ($entityType === 'price_book') {
            $id = (int)DB::table('price_books')->insertGetId([
                'name' => $after['name'] ?? '',
                'version' => (int)($after['version'] ?? 1),
                'currency' => $after['currency'] ?? 'JPY',
                'valid_from' => $after['valid_from'] ?? null,
                'valid_to' => $after['valid_to'] ?? null,
                'memo' => $after['memo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'PRICE_BOOK_CREATED', 'price_book', $id, null, $after);
            return $id;
        }

        if ($entityType === 'price_book_item') {
            $pricingModel = $this->normalizePricingModel((string)($after['pricing_model'] ?? 'FIXED'));
            $hasPricePerM = Schema::hasColumn('price_book_items', 'price_per_m');
            if (!$hasPricePerM && $pricingModel === 'PER_M') {
                $pricingModel = 'PER_MM';
            }

            $insert = [
                'price_book_id' => (int)($after['price_book_id'] ?? 0),
                'sku_id' => (int)($after['sku_id'] ?? 0),
                'pricing_model' => $pricingModel,
                'unit_price' => $after['unit_price'] ?? null,
                'formula' => !empty($after['formula']) ? json_encode($after['formula'], JSON_UNESCAPED_UNICODE) : null,
                'min_qty' => $after['min_qty'] ?? 1,
                'memo' => $after['memo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasPricePerM) {
                $insert['price_per_m'] = $this->resolvePricePerM($after);
            } else {
                $insert['price_per_mm'] = $this->resolvePricePerMm($after);
            }

            $id = (int)DB::table('price_book_items')->insertGetId($insert);
            $this->auditLogger->log($actorId, 'PRICE_BOOK_ITEM_CREATED', 'price_book_item', $id, null, $after);
            return $id;
        }

        if ($entityType === 'product_template') {
            $id = (int)DB::table('product_templates')->insertGetId([
                'template_code' => $after['template_code'] ?? '',
                'name' => $after['name'] ?? '',
                'active' => (bool)($after['active'] ?? true),
                'memo' => $after['memo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'TEMPLATE_CREATED', 'product_template', $id, null, $after);
            return $id;
        }

        if ($entityType === 'product_template_version') {
            $id = (int)DB::table('product_template_versions')->insertGetId([
                'template_id' => (int)($after['template_id'] ?? 0),
                'version' => (int)($after['version'] ?? 1),
                'dsl_version' => (string)($after['dsl_version'] ?? ''),
                'dsl_json' => json_encode($after['dsl_json'] ?? [], JSON_UNESCAPED_UNICODE),
                'active' => (bool)($after['active'] ?? true),
                'memo' => $after['memo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'TEMPLATE_VERSION_CREATED', 'product_template_version', $id, null, $after);
            return $id;
        }

        if ($entityType === 'account_sales_route_permission') {
            $id = $this->applyAccountScopedPermission($after, $actorId);
            $this->auditLogger->log($actorId, 'ACCOUNT_SALES_ROUTE_PERMISSION_CREATED', 'account_sales_route_permission', $id, null, $after);
            return $id;
        }

        return 0;
    }

    private function applyUpdate(string $entityType, int $entityId, mixed $before, mixed $after, int $actorId): int
    {
        $after = is_array($after) ? $after : [];

        if ($entityType === 'account') {
            DB::table('accounts')
                ->whereNull('deleted_at')
                ->where('id', $entityId)
                ->update([
                    'account_type' => $after['account_type'] ?? null,
                    'internal_name' => $after['internal_name'] ?? null,
                    'memo' => $after['memo'] ?? null,
                    'assignee_name' => $after['assignee_name'] ?? null,
                    'updated_at' => now(),
                ]);
            $this->auditLogger->log($actorId, 'ACCOUNT_UPDATED', 'account', $entityId, $before, $after);
            return $entityId;
        }

        if ($entityType === 'account_user_memo') {
            $accountId = (int)($after['account_id'] ?? 0);
            $userId = (int)($after['user_id'] ?? 0);
            DB::table('account_user')
                ->where('account_id', $accountId)
                ->where('user_id', $userId)
                ->update([
                    'memo' => $after['memo'] ?? null,
                    'updated_at' => now(),
                ]);
            $this->auditLogger->log($actorId, 'ACCOUNT_USER_MEMO_UPDATED', 'account_user', null, $before, $after);
            return 0;
        }

        if ($entityType === 'account_sales_route_permission') {
            $payload = is_array($before) ? array_merge($before, $after) : $after;
            $this->applyAccountScopedPermission($payload, $actorId);
            $this->auditLogger->log($actorId, 'ACCOUNT_SALES_ROUTE_PERMISSION_UPDATED', 'account_sales_route_permission', $entityId, $before, $after);
            return $entityId;
        }

        if ($entityType === 'account_sales_route_permission_sync') {
            $accountId = (int)($after['account_id'] ?? 0);
            $selected = is_array($after['catalog_permissions'] ?? null) ? $after['catalog_permissions'] : [];
            $selectedCatalogIds = [];
            foreach ($selected as $line) {
                if (!is_string($line)) {
                    continue;
                }
                $parts = explode(' ', trim($line), 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $method = strtoupper(trim($parts[0]));
                $pattern = $this->normalizePermissionPattern($parts[1]);
                if ($pattern === '' || $accountId <= 0) {
                    continue;
                }

                $catalogId = $this->ensurePermissionCatalog($method, $pattern);
                if ($catalogId <= 0) {
                    continue;
                }
                $selectedCatalogIds[$catalogId] = true;

                $this->upsertAccountPermissionGrantForSalesUsers(
                    $accountId,
                    $catalogId,
                    true,
                    'checkbox',
                    null,
                    $actorId
                );
            }

            $salesUserIds = $this->salesUserIdsInAccount($accountId);
            if (!empty($salesUserIds)) {
                $existingCheckbox = DB::table('work_permission_grants')
                    ->whereIn('user_id', $salesUserIds)
                    ->where('scope_type', 'account')
                    ->where('account_id', $accountId)
                    ->where('effect', 'allow')
                    ->where(function ($q) {
                        $q->where('memo', 'like', 'source:checkbox;%')
                            ->orWhere('memo', 'like', 'migrated:legacy-sales%');
                    })
                    ->get(['id', 'permission_catalog_id']);

                foreach ($existingCheckbox as $grant) {
                    $catalogId = (int)$grant->permission_catalog_id;
                    if (isset($selectedCatalogIds[$catalogId])) {
                        continue;
                    }
                    DB::table('work_permission_grants')
                        ->where('id', (int)$grant->id)
                        ->update([
                            'active' => false,
                            'updated_by' => $actorId,
                            'updated_at' => now(),
                        ]);
                }
            }

            $this->auditLogger->log($actorId, 'ACCOUNT_SALES_ROUTE_PERMISSION_SYNCED', 'account', $accountId, $before, $after);
            return $accountId;
        }

        if ($entityType === 'sku') {
            DB::table('skus')->whereNull('deleted_at')->where('id', $entityId)->update([
                'sku_code' => $after['sku_code'] ?? '',
                'name' => $after['name'] ?? '',
                'category' => $after['category'] ?? 'PROC',
                'active' => (bool)($after['active'] ?? true),
                'attributes' => json_encode($after['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                'memo' => $after['memo'] ?? null,
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'SKU_UPDATED', 'sku', $entityId, $before, $after);
            return $entityId;
        }

        if ($entityType === 'price_book') {
            DB::table('price_books')->whereNull('deleted_at')->where('id', $entityId)->update([
                'name' => $after['name'] ?? '',
                'version' => (int)($after['version'] ?? 1),
                'currency' => $after['currency'] ?? 'JPY',
                'valid_from' => $after['valid_from'] ?? null,
                'valid_to' => $after['valid_to'] ?? null,
                'memo' => $after['memo'] ?? null,
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'PRICE_BOOK_UPDATED', 'price_book', $entityId, $before, $after);
            return $entityId;
        }

        if ($entityType === 'price_book_item') {
            $pricingModel = $this->normalizePricingModel((string)($after['pricing_model'] ?? 'FIXED'));
            $hasPricePerM = Schema::hasColumn('price_book_items', 'price_per_m');
            if (!$hasPricePerM && $pricingModel === 'PER_M') {
                $pricingModel = 'PER_MM';
            }

            $update = [
                'sku_id' => (int)($after['sku_id'] ?? 0),
                'pricing_model' => $pricingModel,
                'unit_price' => $after['unit_price'] ?? null,
                'formula' => !empty($after['formula']) ? json_encode($after['formula'], JSON_UNESCAPED_UNICODE) : null,
                'min_qty' => $after['min_qty'] ?? 1,
                'memo' => $after['memo'] ?? null,
                'updated_at' => now(),
            ];
            if ($hasPricePerM) {
                $update['price_per_m'] = $this->resolvePricePerM($after);
            } else {
                $update['price_per_mm'] = $this->resolvePricePerMm($after);
            }

            DB::table('price_book_items')->whereNull('deleted_at')->where('id', $entityId)->update($update);
            $this->auditLogger->log($actorId, 'PRICE_BOOK_ITEM_UPDATED', 'price_book_item', $entityId, $before, $after);
            return $entityId;
        }

        if ($entityType === 'product_template') {
            DB::table('product_templates')->whereNull('deleted_at')->where('id', $entityId)->update([
                'template_code' => $after['template_code'] ?? '',
                'name' => $after['name'] ?? '',
                'active' => (bool)($after['active'] ?? true),
                'memo' => $after['memo'] ?? null,
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'TEMPLATE_UPDATED', 'product_template', $entityId, $before, $after);
            return $entityId;
        }

        if ($entityType === 'product_template_version') {
            DB::table('product_template_versions')->whereNull('deleted_at')->where('id', $entityId)->update([
                'version' => (int)($after['version'] ?? 1),
                'dsl_version' => (string)($after['dsl_version'] ?? ''),
                'dsl_json' => json_encode($after['dsl_json'] ?? [], JSON_UNESCAPED_UNICODE),
                'active' => (bool)($after['active'] ?? true),
                'memo' => $after['memo'] ?? null,
                'updated_at' => now(),
            ]);
            $this->auditLogger->log($actorId, 'TEMPLATE_VERSION_UPDATED', 'product_template_version', $entityId, $before, $after);
            return $entityId;
        }

        if ($entityType === 'quote') {
            $snapshot = is_array($after['snapshot'] ?? null) ? $after['snapshot'] : [];
            $memo = array_key_exists('memo', $after) ? $after['memo'] : null;
            $update = [
                'updated_at' => now(),
            ];
            if (!empty($snapshot)) {
                $update['snapshot'] = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
                $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
                if (array_key_exists('subtotal', $totals)) {
                    $update['subtotal'] = (float)$totals['subtotal'];
                }
                if (array_key_exists('tax', $totals)) {
                    $update['tax_total'] = (float)$totals['tax'];
                }
                if (array_key_exists('total', $totals)) {
                    $update['total'] = (float)$totals['total'];
                }
            }
            if ($memo !== null) {
                $update['memo'] = $memo;
            }
            DB::table('quotes')->whereNull('deleted_at')->where('id', $entityId)->update($update);
            $this->auditLogger->log($actorId, 'QUOTE_UPDATED', 'quote', $entityId, $before, $after);
            return $entityId;
        }

        return $entityId;
    }

    private function applyDelete(string $entityType, int $entityId, int $actorId, mixed $before): int
    {
        if ($entityId <= 0) {
            if ($entityType !== 'account_sales_route_permission') {
                return 0;
            }
        }

        if ($entityType === 'account_sales_route_permission') {
            $beforeData = is_array($before) ? $before : [];
            $accountId = (int)($beforeData['account_id'] ?? 0);
            $catalogId = (int)($beforeData['permission_catalog_id'] ?? 0);
            if ($catalogId <= 0) {
                $method = strtoupper(trim((string)($beforeData['http_method'] ?? '')));
                $pattern = $this->normalizePermissionPattern((string)($beforeData['uri_pattern'] ?? ''));
                if ($method !== '' && $pattern !== '') {
                    $catalogId = $this->ensurePermissionCatalog($method, $pattern);
                }
            }
            if ($catalogId <= 0 && $entityId > 0) {
                $exists = DB::table('work_permission_catalog')->where('id', $entityId)->exists();
                if ($exists) {
                    $catalogId = $entityId;
                }
            }
            if ($catalogId > 0) {
                $this->upsertAccountPermissionGrantForSalesUsers(
                    $accountId,
                    $catalogId,
                    false,
                    (string)($beforeData['source'] ?? 'manual'),
                    $beforeData['memo'] ?? null,
                    $actorId
                );
            }
            $this->auditLogger->log($actorId, 'ACCOUNT_SALES_ROUTE_PERMISSION_DELETED', 'account_sales_route_permission', $catalogId, $before, null);
            return $catalogId;
        }

        $map = [
            'sku' => 'skus',
            'price_book' => 'price_books',
            'price_book_item' => 'price_book_items',
            'product_template' => 'product_templates',
            'product_template_version' => 'product_template_versions',
            'quote' => 'quotes',
            'account' => 'accounts',
        ];

        $table = $map[$entityType] ?? null;
        if (!$table) {
            return $entityId;
        }

        $update = [
            'deleted_at' => now(),
            'deleted_by' => $actorId,
            'updated_at' => now(),
        ];

        DB::table($table)
            ->whereNull('deleted_at')
            ->where('id', $entityId)
            ->update($update);

        $action = strtoupper($entityType) . '_DELETED';
        $this->auditLogger->log($actorId, $action, $entityType, $entityId, $before, null);

        return $entityId;
    }

    private function normalizePricingModel(string $model): string
    {
        $normalized = strtoupper(trim($model));
        if ($normalized === 'PER_MM') {
            return 'PER_M';
        }

        return $normalized !== '' ? $normalized : 'FIXED';
    }

    private function resolvePricePerM(array $row): ?float
    {
        $value = $row['price_per_m'] ?? null;
        if (is_numeric($value)) {
            return (float)$value;
        }

        $legacyValue = $row['price_per_mm'] ?? null;
        if (is_numeric($legacyValue)) {
            return (float)$legacyValue * 1000;
        }

        return null;
    }

    private function resolvePricePerMm(array $row): ?float
    {
        $legacyValue = $row['price_per_mm'] ?? null;
        if (is_numeric($legacyValue)) {
            return (float)$legacyValue;
        }

        $perM = $this->resolvePricePerM($row);
        return $perM !== null ? ($perM / 1000) : null;
    }

    private function applyAccountScopedPermission(array $after, int $actorId): int
    {
        $accountId = (int)($after['account_id'] ?? 0);
        if ($accountId <= 0) {
            return 0;
        }

        $method = strtoupper(trim((string)($after['http_method'] ?? 'GET')));
        $pattern = $this->normalizePermissionPattern((string)($after['uri_pattern'] ?? '/'));
        if ($pattern === '') {
            return 0;
        }

        $catalogId = $this->upsertPermissionCatalog(
            (int)($after['permission_catalog_id'] ?? 0),
            $method,
            $pattern,
            $after['label'] ?? null
        );
        if ($catalogId <= 0) {
            return 0;
        }

        $this->upsertAccountPermissionGrantForSalesUsers(
            $accountId,
            $catalogId,
            (bool)($after['active'] ?? true),
            (string)($after['source'] ?? 'manual'),
            $after['memo'] ?? null,
            $actorId
        );

        return $catalogId;
    }

    private function upsertPermissionCatalog(int $catalogId, string $method, string $pattern, mixed $label = null): int
    {
        $method = strtoupper(trim($method));
        $pattern = $this->normalizePermissionPattern($pattern);
        if ($pattern === '') {
            return 0;
        }

        $normalizedLabel = trim((string)$label);
        $permissionKey = strtolower($method . ':' . $pattern);

        if ($catalogId > 0) {
            $exists = DB::table('work_permission_catalog')
                ->where('id', $catalogId)
                ->exists();
            if ($exists) {
                $duplicateId = (int)DB::table('work_permission_catalog')
                    ->where('permission_key', $permissionKey)
                    ->where('id', '!=', $catalogId)
                    ->value('id');
                if ($duplicateId > 0) {
                    return $duplicateId;
                }

                $update = [
                    'permission_key' => $permissionKey,
                    'http_method' => $method,
                    'uri_pattern' => $pattern,
                    'active' => true,
                    'updated_at' => now(),
                ];
                if ($normalizedLabel !== '') {
                    $update['label'] = $normalizedLabel;
                }

                DB::table('work_permission_catalog')
                    ->where('id', $catalogId)
                    ->update($update);

                return $catalogId;
            }
        }

        $existingByKey = DB::table('work_permission_catalog')
            ->where('permission_key', $permissionKey)
            ->first(['id']);
        if ($existingByKey) {
            $update = [
                'http_method' => $method,
                'uri_pattern' => $pattern,
                'active' => true,
                'updated_at' => now(),
            ];
            if ($normalizedLabel !== '') {
                $update['label'] = $normalizedLabel;
            }

            DB::table('work_permission_catalog')
                ->where('id', (int)$existingByKey->id)
                ->update($update);
            return (int)$existingByKey->id;
        }

        if ($normalizedLabel === '') {
            $normalizedLabel = 'manual:' . $method . ' ' . $pattern;
        }

        return (int)DB::table('work_permission_catalog')->insertGetId([
            'permission_key' => $permissionKey,
            'http_method' => $method,
            'uri_pattern' => $pattern,
            'label' => $normalizedLabel,
            'default_scope' => 'account',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensurePermissionCatalog(string $method, string $pattern): int
    {
        return $this->upsertPermissionCatalog(0, $method, $pattern);
    }

    private function upsertAccountPermissionGrantForSalesUsers(
        int $accountId,
        int $catalogId,
        bool $active,
        string $source,
        mixed $memo,
        int $actorId
    ): void {
        if ($accountId <= 0 || $catalogId <= 0) {
            return;
        }

        $salesUserIds = $this->salesUserIdsInAccount($accountId);
        if (empty($salesUserIds)) {
            return;
        }

        $encodedMemo = $this->encodeGrantMemo($source, $memo);
        foreach ($salesUserIds as $userId) {
            DB::table('work_permission_grants')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'permission_catalog_id' => $catalogId,
                    'effect' => 'allow',
                    'scope_type' => 'account',
                    'account_id' => $accountId,
                ],
                [
                    'active' => $active,
                    'memo' => $encodedMemo,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function salesUserIdsInAccount(int $accountId): array
    {
        if ($accountId <= 0) {
            return [];
        }

        return DB::table('account_user')
            ->where('account_id', $accountId)
            ->where('role', 'sales')
            ->pluck('user_id')
            ->map(fn ($v): int => (int)$v)
            ->values()
            ->all();
    }

    private function normalizePermissionPattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return '';
        }
        $path = parse_url($pattern, PHP_URL_PATH);
        $path = is_string($path) ? $path : $pattern;
        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    private function normalizePermissionSource(string $source): string
    {
        return strtolower(trim($source)) === 'checkbox' ? 'checkbox' : 'manual';
    }

    private function encodeGrantMemo(string $source, mixed $memo): ?string
    {
        $source = $this->normalizePermissionSource($source);
        $memoValue = trim((string)$memo);
        if ($memoValue === '') {
            return 'source:' . $source . ';';
        }
        return 'source:' . $source . ';' . $memoValue;
    }
}
