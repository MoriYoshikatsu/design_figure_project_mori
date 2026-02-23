<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CatalogIndexService;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SkuController extends Controller
{
    private const CATEGORIES = ['PROC', 'SLEEVE', 'FIBER', 'TUBE', 'CONNECTOR'];

    public function index(Request $request, CatalogIndexService $catalogIndexService)
    {
        $filters = $catalogIndexService->resolveSkuFilters($request, true);
        $panel = $catalogIndexService->buildSkuIndexData($filters);

        return view('work.skus.index', $panel);
    }

    public function create()
    {
        return view('work.skus.create', [
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sku_code' => 'required|string|max:255|unique:skus,sku_code',
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'attributes' => 'nullable|string',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!in_array($data['category'], self::CATEGORIES, true)) {
            return back()->withErrors(['category' => 'categoryが不正です'])->withInput();
        }

        $attrsRaw = (string)($data['attributes'] ?? '');
        $attrs = [];
        if ($attrsRaw !== '') {
            $decoded = json_decode($attrsRaw, true);
            if (!is_array($decoded)) {
                return back()->withErrors(['attributes' => 'attributesはJSON形式で入力してください'])->withInput();
            }
            $attrs = $decoded;
        }

        $active = $request->boolean('active', true);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'sku_code' => $data['sku_code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'active' => $active,
            'attributes' => $attrs,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueCreate(
            'sku',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.skus.index')->with('status', 'SKUの作成申請を送信しました');
    }

    public function edit(int $id)
    {
        $sku = DB::table('skus')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$sku) abort(404);

        $attrs = $sku->attributes ?? '';
        if (is_array($attrs)) {
            $attrs = json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return view('work.skus.edit', [
            'sku' => $sku,
            'attributesJson' => (string)$attrs,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $sku = DB::table('skus')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$sku) abort(404);

        $data = $request->validate([
            'sku_code' => 'required|string|max:255|unique:skus,sku_code,' . $id,
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'attributes' => 'nullable|string',
            'memo' => 'nullable|string|max:5000',
        ]);

        if (!in_array($data['category'], self::CATEGORIES, true)) {
            return back()->withErrors(['category' => 'categoryが不正です'])->withInput();
        }

        $attrsRaw = (string)($data['attributes'] ?? '');
        $attrs = [];
        if ($attrsRaw !== '') {
            $decoded = json_decode($attrsRaw, true);
            if (!is_array($decoded)) {
                return back()->withErrors(['attributes' => 'attributesはJSON形式で入力してください'])->withInput();
            }
            $attrs = $decoded;
        }

        $active = $request->boolean('active', false);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'sku_code' => $data['sku_code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'active' => $active,
            'attributes' => $attrs,
            'memo' => $memo,
        ];
        app(WorkChangeRequestService::class)->queueUpdate(
            'sku',
            $id,
            (array)$sku,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.skus.edit', $id)->with('status', 'SKUの更新申請を送信しました');
    }

    public function destroy(Request $request, int $id)
    {
        $sku = DB::table('skus')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$sku) abort(404);

        app(WorkChangeRequestService::class)->queueDelete(
            'sku',
            $id,
            (array)$sku,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        $tab = (string)$request->input('tab', 'skus');
        if (!in_array($tab, ['skus', 'price_books'], true)) {
            $tab = 'skus';
        }

        return redirect()->route('work.skus.index', ['tab' => $tab])->with('status', 'SKUの削除申請を送信しました');
    }
}
