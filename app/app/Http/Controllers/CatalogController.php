<?php

namespace App\Http\Controllers;

use App\Services\CatalogIndexService;
use App\Services\WorkPermissionService;
use Illuminate\Http\Request;

final class CatalogController extends Controller
{
    public function index(
        Request $request,
        CatalogIndexService $catalogIndexService,
        WorkPermissionService $workPermissionService
    ) {
        $userId = (int)($request->user()?->id ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $canAccessSkus = $workPermissionService->allowsRequest(Request::create('/work/skus', 'GET'), $userId);
        $canAccessPriceBooks = $workPermissionService->allowsRequest(Request::create('/work/price-books', 'GET'), $userId);
        if (!$canAccessSkus && !$canAccessPriceBooks) {
            abort(403);
        }

        $entryRouteName = (string)($request->route()?->getName() ?? 'work.skus.index');
        $routeDefaultTab = $entryRouteName === 'work.price-books.index' ? 'price_books' : 'skus';

        $requestedTab = (string)$request->query('tab', $routeDefaultTab);
        if (!in_array($requestedTab, ['skus', 'price_books'], true)) {
            $requestedTab = $routeDefaultTab;
        }

        $activeTab = $requestedTab;
        if ($activeTab === 'skus' && !$canAccessSkus) {
            $activeTab = $canAccessPriceBooks ? 'price_books' : 'skus';
        } elseif ($activeTab === 'price_books' && !$canAccessPriceBooks) {
            $activeTab = $canAccessSkus ? 'skus' : 'price_books';
        }

        $allowLegacySkuFilters = $entryRouteName === 'work.skus.index';
        $allowLegacyPriceBookFilters = $entryRouteName === 'work.price-books.index';

        $skuFilters = $catalogIndexService->resolveSkuFilters($request, $allowLegacySkuFilters);
        $priceBookFilters = $catalogIndexService->resolvePriceBookFilters($request, $allowLegacyPriceBookFilters);

        $skuPanel = $canAccessSkus
            ? $catalogIndexService->buildSkuIndexData($skuFilters)
            : [
                'skus' => collect(),
                'categories' => [],
                'filters' => $skuFilters,
                'presenceOptions' => [],
            ];

        $priceBookPanel = $canAccessPriceBooks
            ? $catalogIndexService->buildPriceBookIndexData($priceBookFilters)
            : [
                'books' => collect(),
                'filters' => $priceBookFilters,
                'currencyOptions' => [],
                'periodOptions' => [],
                'presenceOptions' => [],
            ];

        return view('work.catalog.index', [
            'entryRouteName' => $entryRouteName,
            'activeTab' => $activeTab,
            'canAccessSkus' => $canAccessSkus,
            'canAccessPriceBooks' => $canAccessPriceBooks,
            'skuPanel' => $skuPanel,
            'priceBookPanel' => $priceBookPanel,
            'skuFilters' => $skuFilters,
            'priceBookFilters' => $priceBookFilters,
        ]);
    }
}
