<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SkuController extends Controller
{
    private const CATEGORIES = ['PROC', 'SLEEVE', 'FIBER', 'TUBE', 'CONNECTOR'];

    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $category = (string)$request->input('category', '');
        $active = (string)$request->input('active', '');
        $hasMemo = (string)$request->input('has_memo', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');
        $updatedFrom = (string)$request->input('updated_from', '');
        $updatedTo = (string)$request->input('updated_to', '');

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

        foreach ($pendingCreates as $req) {
            $payload = app(WorkChangeRequestService::class)->decodePayload($req->proposed_json);
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

        $pendingBySku = DB::table('change_requests')
            ->where('entity_type', 'sku')
            ->where('status', 'PENDING')
            ->whereIn('operation', ['UPDATE', 'DELETE'])
            ->whereIn('entity_id', $skus->filter(fn ($s) => is_numeric((string)$s->id))->pluck('id')->map(fn ($v) => (int)$v)->all())
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

        return view('work.skus.index', [
            'skus' => $skus,
            'categories' => self::CATEGORIES,
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
            'presenceOptions' => [
                'with' => 'あり',
                'without' => 'なし',
            ],
        ]);
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

        return redirect()->route('work.skus.index')->with('status', 'SKUの削除申請を送信しました');
    }
}
