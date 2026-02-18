<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PriceBookItemController extends Controller
{
    private const MODELS = ['FIXED', 'PER_M', 'FORMULA'];

    public function store(Request $request, int $priceBookId)
    {
        $book = DB::table('price_books')->whereNull('deleted_at')->where('id', $priceBookId)->first();
        if (!$book) abort(404);

        $data = $request->validate([
            'sku_id' => 'required|integer',
            'pricing_model' => 'required|string',
            'unit_price' => 'nullable|numeric',
            'price_per_m' => 'nullable|numeric',
            'price_per_mm' => 'nullable|numeric',
            'formula' => 'nullable|string',
            'min_qty' => 'nullable|numeric',
            'memo' => 'nullable|string|max:5000',
        ]);
        $data['pricing_model'] = strtoupper((string)$data['pricing_model']);
        if ($data['pricing_model'] === 'PER_MM') {
            $data['pricing_model'] = 'PER_M';
        }

        if (!in_array($data['pricing_model'], self::MODELS, true)) {
            return back()->withErrors(['pricing_model' => 'pricing_modelが不正です'])->withInput();
        }

        $skuExists = DB::table('skus')->whereNull('deleted_at')->where('id', (int)$data['sku_id'])->exists();
        if (!$skuExists) {
            return back()->withErrors(['sku_id' => 'SKUが存在しません'])->withInput();
        }

        [$unitPrice, $pricePerM, $formula] = $this->normalizePricing($data);
        if ($formula === false) {
            return back()->withErrors(['formula' => 'FORMULAはlinear形式のみ許可しています'])->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'price_book_id' => $priceBookId,
            'sku_id' => (int)$data['sku_id'],
            'pricing_model' => $data['pricing_model'],
            'unit_price' => $unitPrice,
            'price_per_m' => $pricePerM,
            'formula' => $formula ? (json_decode($formula, true) ?: null) : null,
            'min_qty' => $data['min_qty'] ?? 1,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueCreate(
            'price_book_item',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.price-books.edit', $priceBookId)->with('status', '明細の作成申請を送信しました');
    }

    public function edit(int $priceBookId, int $itemId)
    {
        $book = DB::table('price_books')->whereNull('deleted_at')->where('id', $priceBookId)->first();
        if (!$book) abort(404);

        $item = DB::table('price_book_items')
            ->whereNull('deleted_at')
            ->where('id', $itemId)
            ->where('price_book_id', $priceBookId)
            ->first();
        if (!$item) abort(404);
        if (!Schema::hasColumn('price_book_items', 'price_per_m')) {
            $item->price_per_m = is_numeric($item->price_per_mm ?? null)
                ? (float)$item->price_per_mm * 1000
                : null;
            if (($item->pricing_model ?? null) === 'PER_MM') {
                $item->pricing_model = 'PER_M';
            }
        }

        $skus = DB::table('skus')
            ->whereNull('deleted_at')
            ->orderBy('sku_code')
            ->get(['id', 'sku_code', 'name']);

        $formula = $item->formula ?? '';
        if (is_array($formula)) {
            $formula = json_encode($formula, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return view('work.price-books.items.edit', [
            'book' => $book,
            'item' => $item,
            'skus' => $skus,
            'formulaJson' => (string)$formula,
        ]);
    }

    public function update(Request $request, int $priceBookId, int $itemId)
    {
        $item = DB::table('price_book_items')
            ->whereNull('deleted_at')
            ->where('id', $itemId)
            ->where('price_book_id', $priceBookId)
            ->first();
        if (!$item) abort(404);

        $data = $request->validate([
            'sku_id' => 'required|integer',
            'pricing_model' => 'required|string',
            'unit_price' => 'nullable|numeric',
            'price_per_m' => 'nullable|numeric',
            'price_per_mm' => 'nullable|numeric',
            'formula' => 'nullable|string',
            'min_qty' => 'nullable|numeric',
            'memo' => 'nullable|string|max:5000',
        ]);
        $data['pricing_model'] = strtoupper((string)$data['pricing_model']);
        if ($data['pricing_model'] === 'PER_MM') {
            $data['pricing_model'] = 'PER_M';
        }

        if (!in_array($data['pricing_model'], self::MODELS, true)) {
            return back()->withErrors(['pricing_model' => 'pricing_modelが不正です'])->withInput();
        }

        $skuExists = DB::table('skus')->whereNull('deleted_at')->where('id', (int)$data['sku_id'])->exists();
        if (!$skuExists) {
            return back()->withErrors(['sku_id' => 'SKUが存在しません'])->withInput();
        }

        [$unitPrice, $pricePerM, $formula] = $this->normalizePricing($data);
        if ($formula === false) {
            return back()->withErrors(['formula' => 'FORMULAはlinear形式のみ許可しています'])->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'sku_id' => (int)$data['sku_id'],
            'pricing_model' => $data['pricing_model'],
            'unit_price' => $unitPrice,
            'price_per_m' => $pricePerM,
            'formula' => $formula ? (json_decode($formula, true) ?: null) : null,
            'min_qty' => $data['min_qty'] ?? 1,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueUpdate(
            'price_book_item',
            $itemId,
            (array)$item,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.price-books.edit', $priceBookId)->with('status', '明細の更新申請を送信しました');
    }

    public function destroy(Request $request, int $priceBookId, int $itemId)
    {
        $before = DB::table('price_book_items')
            ->whereNull('deleted_at')
            ->where('id', $itemId)
            ->where('price_book_id', $priceBookId)
            ->first();
        if (!$before) {
            abort(404);
        }

        app(WorkChangeRequestService::class)->queueDelete(
            'price_book_item',
            $itemId,
            (array)$before,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.price-books.edit', $priceBookId)->with('status', '明細の削除申請を送信しました');
    }

    /**
     * @return array{0:?float,1:?float,2:string|false|null}
     */
    private function normalizePricing(array $data): array
    {
        $model = $data['pricing_model'];
        $unitPrice = null;
        $pricePerM = null;
        $formula = null;

        if ($model === 'FIXED') {
            $unitPrice = isset($data['unit_price']) ? (float)$data['unit_price'] : null;
        } elseif ($model === 'PER_M') {
            if (isset($data['price_per_m']) && is_numeric($data['price_per_m'])) {
                $pricePerM = (float)$data['price_per_m'];
            } elseif (isset($data['price_per_mm']) && is_numeric($data['price_per_mm'])) {
                $pricePerM = (float)$data['price_per_mm'] * 1000;
            }
        } elseif ($model === 'FORMULA') {
            $raw = (string)($data['formula'] ?? '');
            if ($raw === '') return [null, null, false];
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || ($decoded['type'] ?? null) !== 'linear') {
                return [null, null, false];
            }
            $formula = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return [$unitPrice, $pricePerM, $formula];
    }
}
