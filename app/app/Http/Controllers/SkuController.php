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

        return view('work.parts.index', $panel);
    }

    public function create()
    {
        return view('work.parts.create', [
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'part_code' => 'required|string|max:255|unique:parts,part_code',
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
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
        $nameEn = trim((string)($data['name_en'] ?? ''));
        if ($nameEn === '') $nameEn = null;
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'part_code' => $data['part_code'],
            'name' => $data['name'],
            'name_en' => $nameEn,
            'category' => $data['category'],
            'active' => $active,
            'attributes' => $attrs,
            'memo' => $memo,
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueCreate(
            'part',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.parts.index')->with('status', $changeRequestService->outcomeMessage(
            $submission,
            'Partを作成しました',
            'Partの作成申請を送信しました'
        ));
    }

    public function edit(int $id)
    {
        $sku = DB::table('parts')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$sku) abort(404);

        $attrs = $sku->attributes ?? '';
        if (is_array($attrs)) {
            $attrs = json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return view('work.parts.edit', [
            'sku' => $sku,
            'attributesJson' => (string)$attrs,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $sku = DB::table('parts')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$sku) abort(404);

        $data = $request->validate([
            'part_code' => 'required|string|max:255|unique:parts,part_code,' . $id,
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
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
        $nameEn = trim((string)($data['name_en'] ?? ''));
        if ($nameEn === '') $nameEn = null;
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'part_code' => $data['part_code'],
            'name' => $data['name'],
            'name_en' => $nameEn,
            'category' => $data['category'],
            'active' => $active,
            'attributes' => $attrs,
            'memo' => $memo,
        ];
        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueUpdate(
            'part',
            $id,
            (array)$sku,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.parts.edit', $id)->with('status', $changeRequestService->outcomeMessage(
            $submission,
            'Partを更新しました',
            'Partの更新申請を送信しました'
        ));
    }

    public function destroy(Request $request, int $id)
    {
        $sku = DB::table('parts')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$sku) abort(404);

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueDelete(
            'part',
            $id,
            (array)$sku,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        $tab = (string)$request->input('tab', 'parts');
        if ($tab === 'skus') {
            $tab = 'parts';
        }
        if (!in_array($tab, ['parts', 'price_books'], true)) {
            $tab = 'parts';
        }

        return redirect()->route('work.parts.index', ['tab' => $tab])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            'Partを削除しました',
            'Partの削除申請を送信しました'
        ));
    }
}
