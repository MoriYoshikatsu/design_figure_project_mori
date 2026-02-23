<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CatalogIndexService
{
    /** @var array<int, string> */
    private const SKU_CATEGORIES = ['PROC', 'SLEEVE', 'FIBER', 'TUBE', 'CONNECTOR'];

    /** @var array<string, string> */
    private const PRESENCE_OPTIONS = [
        'with' => 'あり',
        'without' => 'なし',
    ];

    /** @var array<string, string> */
    private const PRICE_BOOK_PERIOD_OPTIONS = [
        'active' => '有効期間内',
        'upcoming' => '開始前',
        'expired' => '期限切れ',
        'no_limit' => '期間指定なし',
    ];

    /**
     * @return array{
     *   q:string,
     *   category:string,
     *   active:string,
     *   has_memo:string,
     *   created_from:string,
     *   created_to:string,
     *   updated_from:string,
     *   updated_to:string
     * }
     */
    public function resolveSkuFilters(Request $request, bool $allowLegacyTopLevel = true): array
    {
        $raw = $request->query('sku');
        $source = is_array($raw) ? $raw : [];

        $keys = [
            'q',
            'category',
            'active',
            'has_memo',
            'created_from',
            'created_to',
            'updated_from',
            'updated_to',
        ];

        if ($allowLegacyTopLevel) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source)) {
                    continue;
                }
                $legacy = $request->query($key);
                if ($legacy !== null) {
                    $source[$key] = $legacy;
                }
            }
        }

        return [
            'q' => trim((string)($source['q'] ?? '')),
            'category' => (string)($source['category'] ?? ''),
            'active' => (string)($source['active'] ?? ''),
            'has_memo' => (string)($source['has_memo'] ?? ''),
            'created_from' => (string)($source['created_from'] ?? ''),
            'created_to' => (string)($source['created_to'] ?? ''),
            'updated_from' => (string)($source['updated_from'] ?? ''),
            'updated_to' => (string)($source['updated_to'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return array{
     *   skus:\Illuminate\Support\Collection<int, object>,
     *   categories:array<int, string>,
     *   filters:array<string, string>,
     *   presenceOptions:array<string, string>
     * }
     */
    public function buildSkuIndexData(array $filters): array
    {
        $isDate = static fn (string $value): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);

        $q = trim((string)($filters['q'] ?? ''));
        $category = (string)($filters['category'] ?? '');
        $active = (string)($filters['active'] ?? '');
        $hasMemo = (string)($filters['has_memo'] ?? '');
        $createdFrom = (string)($filters['created_from'] ?? '');
        $createdTo = (string)($filters['created_to'] ?? '');
        $updatedFrom = (string)($filters['updated_from'] ?? '');
        $updatedTo = (string)($filters['updated_to'] ?? '');

        $query = DB::table('skus')->whereNull('deleted_at');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('cast(id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('sku_code', 'ilike', "%{$q}%")
                    ->orWhere('name', 'ilike', "%{$q}%")
                    ->orWhere('category', 'ilike', "%{$q}%")
                    ->orWhere('memo', 'ilike', "%{$q}%");
            });
        }
        if ($category !== '') {
            $query->where('category', $category);
        }
        if ($active === '1') {
            $query->where('active', true);
        } elseif ($active === '0') {
            $query->where('active', false);
        }
        if ($hasMemo === 'with') {
            $query->whereNotNull('memo')->where('memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('memo')->orWhere('memo', '');
            });
        }
        if ($createdFrom !== '' && $isDate($createdFrom)) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }
        if ($createdTo !== '' && $isDate($createdTo)) {
            $query->whereDate('created_at', '<=', $createdTo);
        }
        if ($updatedFrom !== '' && $isDate($updatedFrom)) {
            $query->whereDate('updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo !== '' && $isDate($updatedTo)) {
            $query->whereDate('updated_at', '<=', $updatedTo);
        }

        $skus = $query->orderBy('id', 'desc')->limit(200)->get();

        $pendingCreates = DB::table('change_requests')
            ->where('entity_type', 'sku')
            ->where('operation', 'CREATE')
            ->where('status', 'PENDING')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'proposed_json', 'created_at']);

        /** @var WorkChangeRequestService $requestService */
        $requestService = app(WorkChangeRequestService::class);

        foreach ($pendingCreates as $req) {
            $payload = $requestService->decodePayload($req->proposed_json);
            $after = is_array($payload['after'] ?? null) ? $payload['after'] : [];
            $virtual = (object)[
                'id' => 'REQ-' . $req->id,
                'sku_code' => (string)($after['sku_code'] ?? ''),
                'name' => (string)($after['name'] ?? ''),
                'category' => (string)($after['category'] ?? ''),
                'active' => (bool)($after['active'] ?? true),
                'memo' => (string)($after['memo'] ?? ''),
                'created_at' => $req->created_at,
                'updated_at' => $req->created_at,
                'is_pending_create' => true,
                'pending_request_id' => (int)$req->id,
                'pending_operation' => 'CREATE',
            ];
            $skus->prepend($virtual);
        }

        $realSkuIds = $skus
            ->filter(static fn ($sku) => is_numeric((string)$sku->id))
            ->pluck('id')
            ->map(static fn ($value) => (int)$value)
            ->all();

        if (!empty($realSkuIds)) {
            $pendingBySku = DB::table('change_requests')
                ->where('entity_type', 'sku')
                ->where('status', 'PENDING')
                ->whereIn('operation', ['UPDATE', 'DELETE'])
                ->whereIn('entity_id', $realSkuIds)
                ->orderByDesc('id')
                ->get(['entity_id', 'operation'])
                ->groupBy('entity_id');

            foreach ($skus as $sku) {
                if (!is_numeric((string)$sku->id)) {
                    continue;
                }
                $rows = $pendingBySku->get((int)$sku->id);
                if (!$rows || $rows->isEmpty()) {
                    continue;
                }
                $sku->pending_operation = (string)$rows->first()->operation;
            }
        }

        return [
            'skus' => $skus,
            'categories' => self::SKU_CATEGORIES,
            'filters' => [
                'q' => $q,
                'category' => $category,
                'active' => $active,
                'has_memo' => $hasMemo,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'updated_from' => $updatedFrom,
                'updated_to' => $updatedTo,
            ],
            'presenceOptions' => self::PRESENCE_OPTIONS,
        ];
    }

    /**
     * @return array{
     *   q:string,
     *   currency:string,
     *   period:string,
     *   version_min:string,
     *   version_max:string,
     *   has_memo:string,
     *   valid_from_from:string,
     *   valid_from_to:string,
     *   valid_to_from:string,
     *   valid_to_to:string,
     *   created_from:string,
     *   created_to:string,
     *   updated_from:string,
     *   updated_to:string
     * }
     */
    public function resolvePriceBookFilters(Request $request, bool $allowLegacyTopLevel = true): array
    {
        $raw = $request->query('pb');
        $source = is_array($raw) ? $raw : [];

        $keys = [
            'q',
            'currency',
            'period',
            'version_min',
            'version_max',
            'has_memo',
            'valid_from_from',
            'valid_from_to',
            'valid_to_from',
            'valid_to_to',
            'created_from',
            'created_to',
            'updated_from',
            'updated_to',
        ];

        if ($allowLegacyTopLevel) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source)) {
                    continue;
                }
                $legacy = $request->query($key);
                if ($legacy !== null) {
                    $source[$key] = $legacy;
                }
            }
        }

        return [
            'q' => trim((string)($source['q'] ?? '')),
            'currency' => (string)($source['currency'] ?? ''),
            'period' => (string)($source['period'] ?? ''),
            'version_min' => (string)($source['version_min'] ?? ''),
            'version_max' => (string)($source['version_max'] ?? ''),
            'has_memo' => (string)($source['has_memo'] ?? ''),
            'valid_from_from' => (string)($source['valid_from_from'] ?? ''),
            'valid_from_to' => (string)($source['valid_from_to'] ?? ''),
            'valid_to_from' => (string)($source['valid_to_from'] ?? ''),
            'valid_to_to' => (string)($source['valid_to_to'] ?? ''),
            'created_from' => (string)($source['created_from'] ?? ''),
            'created_to' => (string)($source['created_to'] ?? ''),
            'updated_from' => (string)($source['updated_from'] ?? ''),
            'updated_to' => (string)($source['updated_to'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return array{
     *   books:\Illuminate\Support\Collection<int, object>,
     *   filters:array<string, string>,
     *   currencyOptions:array<int, string>,
     *   periodOptions:array<string, string>,
     *   presenceOptions:array<string, string>
     * }
     */
    public function buildPriceBookIndexData(array $filters): array
    {
        $isDate = static fn (string $value): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);

        $q = trim((string)($filters['q'] ?? ''));
        $currency = (string)($filters['currency'] ?? '');
        $period = (string)($filters['period'] ?? '');
        $versionMin = (string)($filters['version_min'] ?? '');
        $versionMax = (string)($filters['version_max'] ?? '');
        $hasMemo = (string)($filters['has_memo'] ?? '');
        $validFromFrom = (string)($filters['valid_from_from'] ?? '');
        $validFromTo = (string)($filters['valid_from_to'] ?? '');
        $validToFrom = (string)($filters['valid_to_from'] ?? '');
        $validToTo = (string)($filters['valid_to_to'] ?? '');
        $createdFrom = (string)($filters['created_from'] ?? '');
        $createdTo = (string)($filters['created_to'] ?? '');
        $updatedFrom = (string)($filters['updated_from'] ?? '');
        $updatedTo = (string)($filters['updated_to'] ?? '');

        $query = DB::table('price_books')->whereNull('deleted_at');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('cast(id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('name', 'ilike', "%{$q}%")
                    ->orWhereRaw('cast(version as text) ilike ?', ["%{$q}%"])
                    ->orWhere('currency', 'ilike', "%{$q}%")
                    ->orWhere('memo', 'ilike', "%{$q}%");
            });
        }
        if ($currency !== '') {
            $query->where('currency', $currency);
        }
        if ($versionMin !== '' && is_numeric($versionMin)) {
            $query->where('version', '>=', (int)$versionMin);
        }
        if ($versionMax !== '' && is_numeric($versionMax)) {
            $query->where('version', '<=', (int)$versionMax);
        }
        if ($hasMemo === 'with') {
            $query->whereNotNull('memo')->where('memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $query->where(function ($sub) {
                $sub->whereNull('memo')->orWhere('memo', '');
            });
        }
        if ($validFromFrom !== '' && $isDate($validFromFrom)) {
            $query->whereDate('valid_from', '>=', $validFromFrom);
        }
        if ($validFromTo !== '' && $isDate($validFromTo)) {
            $query->whereDate('valid_from', '<=', $validFromTo);
        }
        if ($validToFrom !== '' && $isDate($validToFrom)) {
            $query->whereDate('valid_to', '>=', $validToFrom);
        }
        if ($validToTo !== '' && $isDate($validToTo)) {
            $query->whereDate('valid_to', '<=', $validToTo);
        }
        if ($createdFrom !== '' && $isDate($createdFrom)) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }
        if ($createdTo !== '' && $isDate($createdTo)) {
            $query->whereDate('created_at', '<=', $createdTo);
        }
        if ($updatedFrom !== '' && $isDate($updatedFrom)) {
            $query->whereDate('updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo !== '' && $isDate($updatedTo)) {
            $query->whereDate('updated_at', '<=', $updatedTo);
        }

        $today = now()->toDateString();
        if ($period === 'active') {
            $query->where(function ($sub) use ($today) {
                $sub->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today);
            })->where(function ($sub) use ($today) {
                $sub->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today);
            });
        } elseif ($period === 'upcoming') {
            $query->whereNotNull('valid_from')->whereDate('valid_from', '>', $today);
        } elseif ($period === 'expired') {
            $query->whereNotNull('valid_to')->whereDate('valid_to', '<', $today);
        } elseif ($period === 'no_limit') {
            $query->whereNull('valid_from')->whereNull('valid_to');
        }

        $books = $query->orderBy('id', 'desc')->limit(200)->get();

        $pendingCreates = DB::table('change_requests')
            ->where('entity_type', 'price_book')
            ->where('operation', 'CREATE')
            ->where('status', 'PENDING')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'proposed_json', 'created_at']);

        /** @var WorkChangeRequestService $requestService */
        $requestService = app(WorkChangeRequestService::class);

        foreach ($pendingCreates as $req) {
            $payload = $requestService->decodePayload($req->proposed_json);
            $after = is_array($payload['after'] ?? null) ? $payload['after'] : [];
            $virtual = (object)[
                'id' => 'REQ-' . $req->id,
                'name' => (string)($after['name'] ?? ''),
                'version' => (int)($after['version'] ?? 0),
                'currency' => (string)($after['currency'] ?? ''),
                'valid_from' => $after['valid_from'] ?? null,
                'valid_to' => $after['valid_to'] ?? null,
                'memo' => (string)($after['memo'] ?? ''),
                'created_at' => $req->created_at,
                'updated_at' => $req->created_at,
                'is_pending_create' => true,
                'pending_request_id' => (int)$req->id,
                'pending_operation' => 'CREATE',
            ];
            $books->prepend($virtual);
        }

        $bookIds = $books
            ->filter(static fn ($book) => is_numeric((string)$book->id))
            ->pluck('id')
            ->map(static fn ($value) => (int)$value)
            ->all();

        if (!empty($bookIds)) {
            $pendingByBook = DB::table('change_requests')
                ->where('entity_type', 'price_book')
                ->where('status', 'PENDING')
                ->whereIn('operation', ['UPDATE', 'DELETE'])
                ->whereIn('entity_id', $bookIds)
                ->orderByDesc('id')
                ->get(['entity_id', 'operation'])
                ->groupBy('entity_id');

            foreach ($books as $book) {
                if (!is_numeric((string)$book->id)) {
                    continue;
                }
                $rows = $pendingByBook->get((int)$book->id);
                if ($rows && !$rows->isEmpty()) {
                    $book->pending_operation = (string)$rows->first()->operation;
                }
            }
        }

        $currencyOptions = DB::table('price_books')
            ->whereNull('deleted_at')
            ->whereNotNull('currency')
            ->where('currency', '<>', '')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();

        return [
            'books' => $books,
            'filters' => [
                'q' => $q,
                'currency' => $currency,
                'period' => $period,
                'version_min' => $versionMin,
                'version_max' => $versionMax,
                'has_memo' => $hasMemo,
                'valid_from_from' => $validFromFrom,
                'valid_from_to' => $validFromTo,
                'valid_to_from' => $validToFrom,
                'valid_to_to' => $validToTo,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'updated_from' => $updatedFrom,
                'updated_to' => $updatedTo,
            ],
            'currencyOptions' => $currencyOptions,
            'periodOptions' => self::PRICE_BOOK_PERIOD_OPTIONS,
            'presenceOptions' => self::PRESENCE_OPTIONS,
        ];
    }
}
