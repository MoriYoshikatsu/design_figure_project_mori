<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminPriceBookController extends Controller
{
    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $currency = (string)$request->input('currency', '');
        $period = (string)$request->input('period', '');
        $versionMin = (string)$request->input('version_min', '');
        $versionMax = (string)$request->input('version_max', '');
        $hasMemo = (string)$request->input('has_memo', '');
        $validFromFrom = (string)$request->input('valid_from_from', '');
        $validFromTo = (string)$request->input('valid_from_to', '');
        $validToFrom = (string)$request->input('valid_to_from', '');
        $validToTo = (string)$request->input('valid_to_to', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');
        $updatedFrom = (string)$request->input('updated_from', '');
        $updatedTo = (string)$request->input('updated_to', '');

        $query = DB::table('price_books');

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

        $currencyOptions = DB::table('price_books')
            ->whereNotNull('currency')
            ->where('currency', '<>', '')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();

        return view('admin.price-books.index', [
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
            'periodOptions' => [
                'active' => '有効期間内',
                'upcoming' => '開始前',
                'expired' => '期限切れ',
                'no_limit' => '期間指定なし',
            ],
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
    }

    public function create()
    {
        return view('admin.price-books.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'required|integer|min:1',
            'currency' => 'required|string|max:3',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!empty($data['valid_from']) && !empty($data['valid_to']) && $data['valid_from'] > $data['valid_to']) {
            return back()->withErrors(['valid_to' => 'valid_toはvalid_from以降の日付にしてください'])->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $id = (int)DB::table('price_books')->insertGetId([
            'name' => $data['name'],
            'version' => $data['version'],
            'currency' => $data['currency'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_to' => $data['valid_to'] ?: null,
            'memo' => $memo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AuditLogger::class)->log((int)auth()->id(), 'PRICE_BOOK_CREATED', 'price_book', $id, null, [
            'id' => $id,
            'name' => $data['name'],
            'version' => $data['version'],
            'currency' => $data['currency'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_to' => $data['valid_to'] ?: null,
            'memo' => $memo,
        ]);

        return redirect()->route('admin.price-books.index')->with('status', '価格表を作成しました');
    }

    public function edit(Request $request, int $id)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $book = DB::table('price_books')->where('id', $id)->first();
        if (!$book) abort(404);

        $itemQ = trim((string)$request->input('item_q', ''));
        $pricingModel = (string)$request->input('pricing_model', '');
        $skuId = (string)$request->input('sku_id', '');
        $hasMemo = (string)$request->input('item_has_memo', '');
        $minQtyMin = (string)$request->input('min_qty_min', '');
        $minQtyMax = (string)$request->input('min_qty_max', '');
        $unitPriceMin = (string)$request->input('unit_price_min', '');
        $unitPriceMax = (string)$request->input('unit_price_max', '');
        $pricePerMmMin = (string)$request->input('price_per_mm_min', '');
        $pricePerMmMax = (string)$request->input('price_per_mm_max', '');
        $updatedFrom = (string)$request->input('item_updated_from', '');
        $updatedTo = (string)$request->input('item_updated_to', '');

        $itemsQuery = DB::table('price_book_items as p')
            ->join('skus as s', 's.id', '=', 'p.sku_id')
            ->where('p.price_book_id', $id)
            ->select([
                'p.id',
                'p.pricing_model',
                'p.unit_price',
                'p.price_per_mm',
                'p.formula',
                'p.min_qty',
                'p.memo',
                'p.updated_at',
                'p.created_at',
                'p.sku_id',
                's.sku_code',
                's.name as sku_name',
            ]);

        if ($itemQ !== '') {
            $itemsQuery->where(function ($sub) use ($itemQ) {
                $sub->whereRaw('cast(p.id as text) ilike ?', ["%{$itemQ}%"])
                    ->orWhere('s.sku_code', 'ilike', "%{$itemQ}%")
                    ->orWhere('s.name', 'ilike', "%{$itemQ}%")
                    ->orWhere('p.pricing_model', 'ilike', "%{$itemQ}%")
                    ->orWhereRaw('cast(p.min_qty as text) ilike ?', ["%{$itemQ}%"])
                    ->orWhereRaw('cast(p.unit_price as text) ilike ?', ["%{$itemQ}%"])
                    ->orWhereRaw('cast(p.price_per_mm as text) ilike ?', ["%{$itemQ}%"])
                    ->orWhere('p.memo', 'ilike', "%{$itemQ}%")
                    ->orWhereRaw('cast(p.formula as text) ilike ?', ["%{$itemQ}%"]);
            });
        }
        if ($pricingModel !== '') {
            $itemsQuery->where('p.pricing_model', $pricingModel);
        }
        if ($skuId !== '' && is_numeric($skuId)) {
            $itemsQuery->where('p.sku_id', (int)$skuId);
        }
        if ($hasMemo === 'with') {
            $itemsQuery->whereNotNull('p.memo')->where('p.memo', '<>', '');
        } elseif ($hasMemo === 'without') {
            $itemsQuery->where(function ($sub) {
                $sub->whereNull('p.memo')->orWhere('p.memo', '');
            });
        }
        if ($minQtyMin !== '' && is_numeric($minQtyMin)) {
            $itemsQuery->where('p.min_qty', '>=', (float)$minQtyMin);
        }
        if ($minQtyMax !== '' && is_numeric($minQtyMax)) {
            $itemsQuery->where('p.min_qty', '<=', (float)$minQtyMax);
        }
        if ($unitPriceMin !== '' && is_numeric($unitPriceMin)) {
            $itemsQuery->where('p.unit_price', '>=', (float)$unitPriceMin);
        }
        if ($unitPriceMax !== '' && is_numeric($unitPriceMax)) {
            $itemsQuery->where('p.unit_price', '<=', (float)$unitPriceMax);
        }
        if ($pricePerMmMin !== '' && is_numeric($pricePerMmMin)) {
            $itemsQuery->where('p.price_per_mm', '>=', (float)$pricePerMmMin);
        }
        if ($pricePerMmMax !== '' && is_numeric($pricePerMmMax)) {
            $itemsQuery->where('p.price_per_mm', '<=', (float)$pricePerMmMax);
        }
        if ($updatedFrom !== '' && $isDate($updatedFrom)) {
            $itemsQuery->whereDate('p.updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo !== '' && $isDate($updatedTo)) {
            $itemsQuery->whereDate('p.updated_at', '<=', $updatedTo);
        }

        $items = $itemsQuery->orderBy('p.id')->get();

        $skus = DB::table('skus')->orderBy('sku_code')->get(['id', 'sku_code', 'name']);

        return view('admin.price-books.edit', [
            'book' => $book,
            'items' => $items,
            'skus' => $skus,
            'itemFilters' => [
                'item_q' => $itemQ,
                'pricing_model' => $pricingModel,
                'sku_id' => $skuId,
                'item_has_memo' => $hasMemo,
                'min_qty_min' => $minQtyMin,
                'min_qty_max' => $minQtyMax,
                'unit_price_min' => $unitPriceMin,
                'unit_price_max' => $unitPriceMax,
                'price_per_mm_min' => $pricePerMmMin,
                'price_per_mm_max' => $pricePerMmMax,
                'item_updated_from' => $updatedFrom,
                'item_updated_to' => $updatedTo,
            ],
            'pricingModelOptions' => ['FIXED', 'PER_MM', 'FORMULA'],
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $book = DB::table('price_books')->where('id', $id)->first();
        if (!$book) abort(404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'required|integer|min:1',
            'currency' => 'required|string|max:3',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!empty($data['valid_from']) && !empty($data['valid_to']) && $data['valid_from'] > $data['valid_to']) {
            return back()->withErrors(['valid_to' => 'valid_toはvalid_from以降の日付にしてください'])->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $before = (array)$book;
        DB::table('price_books')->where('id', $id)->update([
            'name' => $data['name'],
            'version' => $data['version'],
            'currency' => $data['currency'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_to' => $data['valid_to'] ?: null,
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('price_books')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'PRICE_BOOK_UPDATED', 'price_book', $id, $before, $after);

        return redirect()->route('admin.price-books.edit', $id)->with('status', '価格表を更新しました');
    }
}
