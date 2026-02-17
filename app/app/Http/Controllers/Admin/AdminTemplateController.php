<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminTemplateController extends Controller
{
    public function index(Request $request)
    {
        $isDate = static fn (string $v): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        $q = trim((string)$request->input('q', ''));
        $active = (string)$request->input('active', '');
        $hasMemo = (string)$request->input('has_memo', '');
        $createdFrom = (string)$request->input('created_from', '');
        $createdTo = (string)$request->input('created_to', '');
        $updatedFrom = (string)$request->input('updated_from', '');
        $updatedTo = (string)$request->input('updated_to', '');

        $query = DB::table('product_templates');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('cast(id as text) ilike ?', ["%{$q}%"])
                    ->orWhere('template_code', 'ilike', "%{$q}%")
                    ->orWhere('name', 'ilike', "%{$q}%")
                    ->orWhere('memo', 'ilike', "%{$q}%");
            });
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

        $templates = $query->orderBy('id', 'desc')->limit(200)->get();
        return view('admin.templates.index', [
            'templates' => $templates,
            'filters' => [
                'q' => $q,
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
        return view('admin.templates.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'template_code' => 'required|string|max:255|unique:product_templates,template_code',
            'name' => 'required|string|max:255',
            'memo' => 'nullable|string|max:5000',
        ]);

        $active = $request->boolean('active', true);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $id = (int)DB::table('product_templates')->insertGetId([
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
            'memo' => $memo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AuditLogger::class)->log((int)auth()->id(), 'TEMPLATE_CREATED', 'product_template', $id, null, [
            'id' => $id,
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
            'memo' => $memo,
        ]);

        return redirect()->route('admin.templates.index')->with('status', 'テンプレを作成しました');
    }

    public function edit(int $id)
    {
        $template = DB::table('product_templates')->where('id', $id)->first();
        if (!$template) abort(404);

        $versions = DB::table('product_template_versions')
            ->where('template_id', $id)
            ->orderBy('version', 'desc')
            ->get();

        $nextVersion = (int)DB::table('product_template_versions')
            ->where('template_id', $id)
            ->max('version') + 1;

        return view('admin.templates.edit', [
            'template' => $template,
            'versions' => $versions,
            'nextVersion' => $nextVersion,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $template = DB::table('product_templates')->where('id', $id)->first();
        if (!$template) abort(404);

        $data = $request->validate([
            'template_code' => 'required|string|max:255|unique:product_templates,template_code,' . $id,
            'name' => 'required|string|max:255',
            'memo' => 'nullable|string|max:5000',
        ]);

        $active = $request->boolean('active', false);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $before = (array)$template;
        DB::table('product_templates')->where('id', $id)->update([
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
            'memo' => $memo,
            'updated_at' => now(),
        ]);

        $after = (array)DB::table('product_templates')->where('id', $id)->first();
        app(AuditLogger::class)->log((int)auth()->id(), 'TEMPLATE_UPDATED', 'product_template', $id, $before, $after);

        return redirect()->route('admin.templates.edit', $id)->with('status', 'テンプレを更新しました');
    }
}
