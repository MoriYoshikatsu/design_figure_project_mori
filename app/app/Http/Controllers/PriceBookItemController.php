<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PriceBookItemController extends Controller
{
    private const MODELS = ['FIXED', 'PER_MM', 'FORMULA'];

    public function store(Request $request, int $priceBookId)
    {
        $book = DB::table('price_books')->whereNull('deleted_at')->where('id', $priceBookId)->first();
        if (!$book) abort(404);

        $data = $request->validate([
            'sku_id' => 'required|integer',
            'pricing_model' => 'required|string',
            'unit_price' => 'nullable|numeric',
            'price_per_mm' => 'nullable|numeric',
            'formula' => 'nullable|string',
            'min_qty' => 'nullable|numeric',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!in_array($data['pricing_model'], self::MODELS, true)) {
            return back()->withErrors(['pricing_model' => 'pricing_modelが不正です'])->withInput();
        }

        $skuExists = DB::table('skus')->whereNull('deleted_at')->where('id', (int)$data['sku_id'])->exists();
        if (!$skuExists) {
            return back()->withErrors(['sku_id' => 'SKUが存在しません'])->withInput();
        }

        [$unitPrice, $pricePerMm, $formula] = $this->normalizePricing($data);
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
            'price_per_mm' => $pricePerMm,
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
            'price_per_mm' => 'nullable|numeric',
            'formula' => 'nullable|string',
            'min_qty' => 'nullable|numeric',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!in_array($data['pricing_model'], self::MODELS, true)) {
            return back()->withErrors(['pricing_model' => 'pricing_modelが不正です'])->withInput();
        }

        $skuExists = DB::table('skus')->whereNull('deleted_at')->where('id', (int)$data['sku_id'])->exists();
        if (!$skuExists) {
            return back()->withErrors(['sku_id' => 'SKUが存在しません'])->withInput();
        }

        [$unitPrice, $pricePerMm, $formula] = $this->normalizePricing($data);
        if ($formula === false) {
            return back()->withErrors(['formula' => 'FORMULAはlinear形式のみ許可しています'])->withInput();
        }

        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'sku_id' => (int)$data['sku_id'],
            'pricing_model' => $data['pricing_model'],
            'unit_price' => $unitPrice,
            'price_per_mm' => $pricePerMm,
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
        $pricePerMm = null;
        $formula = null;

        if ($model === 'FIXED') {
            $unitPrice = isset($data['unit_price']) ? (float)$data['unit_price'] : null;
        } elseif ($model === 'PER_MM') {
            $pricePerMm = isset($data['price_per_mm']) ? (float)$data['price_per_mm'] : null;
        } elseif ($model === 'FORMULA') {
            $raw = (string)($data['formula'] ?? '');
            if ($raw === '') return [null, null, false];
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || ($decoded['type'] ?? null) !== 'linear') {
                return [null, null, false];
            }
            $formula = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return [$unitPrice, $pricePerMm, $formula];
    }
}
