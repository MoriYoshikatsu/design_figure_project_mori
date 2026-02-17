<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminSkuController extends Controller
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

        $query = DB::table('skus');
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

        return view('admin.skus.index', [
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
        return view('admin.skus.create', [
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

        $id = (int)DB::table('skus')->insertGetId([
            'sku_code' => $data['sku_code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'active' => $active,
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'memo' => $memo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AuditLogger::class)->log((int)auth()->id(), 'SKU_CREATED', 'sku', $id, null, [
            'id' => $id,
            'sku_code' => $data['sku_code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'active' => $active,
            'attributes' => $attrs,
            'memo' => $memo,
        ]);

        return redirect()->route('admin.skus.index')->with('status', 'SKUを作成しました');
    }

    public function edit(int $id)
    {
        $sku = DB::table('skus')->where('id', $id)->first();
        if (!$sku) abort(404);

        $attrs = $sku->attributes ?? '';
        if (is_array($attrs)) {
            $attrs = json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return view('admin.skus.edit', [
            'sku' => $sku,
            'attributesJson' => (string)$attrs,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $sku = DB::table('skus')->where('id', $id)->first();
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

        $before = (array)$sku;
        DB::table('skus')->where('id', $id)->update([
            'sku_code' => $data['sku_code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'active' => $active,
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('skus')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'SKU_UPDATED', 'sku', $id, $before, $after);

        return redirect()->route('admin.skus.edit', $id)->with('status', 'SKUを更新しました');
    }
}
