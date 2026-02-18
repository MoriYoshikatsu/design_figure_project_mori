<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TemplateController extends Controller
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

        $query = DB::table('product_templates')->whereNull('deleted_at');
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

        $pendingCreates = DB::table('change_requests')
            ->where('entity_type', 'product_template')
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
                'template_code' => (string)($after['template_code'] ?? ''),
                'name' => (string)($after['name'] ?? ''),
                'active' => (bool)($after['active'] ?? true),
                'memo' => (string)($after['memo'] ?? ''),
                'created_at' => $req->created_at,
                'updated_at' => $req->created_at,
                'is_pending_create' => true,
                'pending_request_id' => (int)$req->id,
                'pending_operation' => 'CREATE',
            ];
            $templates->prepend($virtual);
        }

        $templateIds = $templates
            ->filter(fn ($t) => is_numeric((string)$t->id))
            ->pluck('id')
            ->map(fn ($v) => (int)$v)
            ->all();
        if (!empty($templateIds)) {
            $pendingByTemplate = DB::table('change_requests')
                ->where('entity_type', 'product_template')
                ->where('status', 'PENDING')
                ->whereIn('operation', ['UPDATE', 'DELETE'])
                ->whereIn('entity_id', $templateIds)
                ->orderByDesc('id')
                ->get(['entity_id', 'operation'])
                ->groupBy('entity_id');
            foreach ($templates as $template) {
                if (!is_numeric((string)$template->id)) {
                    continue;
                }
                $rows = $pendingByTemplate->get((int)$template->id);
                if ($rows && !$rows->isEmpty()) {
                    $template->pending_operation = (string)$rows->first()->operation;
                }
            }
        }
        return view('work.templates.index', [
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
        return view('work.templates.create');
    }

    public function show(int $id)
    {
        $template = DB::table('product_templates')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$template) abort(404);

        $templatePendingOperation = DB::table('change_requests')
            ->where('entity_type', 'product_template')
            ->where('entity_id', $id)
            ->where('status', 'PENDING')
            ->whereIn('operation', ['UPDATE', 'DELETE'])
            ->orderByDesc('id')
            ->value('operation');

        $versions = DB::table('product_template_versions')
            ->where('template_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('version', 'desc')
            ->get();

        $pendingVersionCreates = DB::table('change_requests')
            ->where('entity_type', 'product_template_version')
            ->where('operation', 'CREATE')
            ->where('status', 'PENDING')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'proposed_json', 'created_at']);
        foreach ($pendingVersionCreates as $req) {
            $payload = app(WorkChangeRequestService::class)->decodePayload($req->proposed_json);
            $after = is_array($payload['after'] ?? null) ? $payload['after'] : [];
            if ((int)($after['template_id'] ?? 0) !== $id) {
                continue;
            }

            $virtual = (object)[
                'id' => 'REQ-' . $req->id,
                'version' => (int)($after['version'] ?? 0),
                'dsl_version' => (string)($after['dsl_version'] ?? ''),
                'active' => (bool)($after['active'] ?? true),
                'memo' => $after['memo'] ?? null,
                'updated_at' => $req->created_at,
                'is_pending_create' => true,
                'pending_operation' => 'CREATE',
            ];
            $versions->prepend($virtual);
        }

        $versionIds = $versions
            ->filter(fn ($version) => is_numeric((string)$version->id))
            ->pluck('id')
            ->map(fn ($v) => (int)$v)
            ->all();
        if (!empty($versionIds)) {
            $pendingByVersion = DB::table('change_requests')
                ->where('entity_type', 'product_template_version')
                ->where('status', 'PENDING')
                ->whereIn('operation', ['UPDATE', 'DELETE'])
                ->whereIn('entity_id', $versionIds)
                ->orderByDesc('id')
                ->get(['entity_id', 'operation'])
                ->groupBy('entity_id');
            foreach ($versions as $version) {
                if (!is_numeric((string)$version->id)) {
                    continue;
                }
                $rows = $pendingByVersion->get((int)$version->id);
                if ($rows && !$rows->isEmpty()) {
                    $version->pending_operation = (string)$rows->first()->operation;
                }
            }
        }

        return view('work.templates.show', [
            'template' => $template,
            'versions' => $versions,
            'templatePendingOperation' => $templatePendingOperation,
        ]);
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

        $after = [
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueCreate(
            'product_template',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.templates.index')->with('status', 'テンプレの作成申請を送信しました');
    }

    public function edit(int $id)
    {
        $template = DB::table('product_templates')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$template) abort(404);

        $versions = DB::table('product_template_versions')
            ->where('template_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('version', 'desc')
            ->get();

        $pendingVersionCreates = DB::table('change_requests')
            ->where('entity_type', 'product_template_version')
            ->where('operation', 'CREATE')
            ->where('status', 'PENDING')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'proposed_json', 'created_at']);
        foreach ($pendingVersionCreates as $req) {
            $payload = app(WorkChangeRequestService::class)->decodePayload($req->proposed_json);
            $after = is_array($payload['after'] ?? null) ? $payload['after'] : [];
            if ((int)($after['template_id'] ?? 0) !== $id) {
                continue;
            }

            $virtual = (object)[
                'id' => 'REQ-' . $req->id,
                'version' => (int)($after['version'] ?? 0),
                'dsl_version' => (string)($after['dsl_version'] ?? ''),
                'active' => (bool)($after['active'] ?? true),
                'memo' => $after['memo'] ?? null,
                'created_at' => $req->created_at,
                'updated_at' => $req->created_at,
                'is_pending_create' => true,
                'pending_operation' => 'CREATE',
            ];
            $versions->prepend($virtual);
        }

        $versionIds = $versions
            ->filter(fn ($version) => is_numeric((string)$version->id))
            ->pluck('id')
            ->map(fn ($v) => (int)$v)
            ->all();
        if (!empty($versionIds)) {
            $pendingByVersion = DB::table('change_requests')
                ->where('entity_type', 'product_template_version')
                ->where('status', 'PENDING')
                ->whereIn('operation', ['UPDATE', 'DELETE'])
                ->whereIn('entity_id', $versionIds)
                ->orderByDesc('id')
                ->get(['entity_id', 'operation'])
                ->groupBy('entity_id');
            foreach ($versions as $version) {
                if (!is_numeric((string)$version->id)) {
                    continue;
                }
                $rows = $pendingByVersion->get((int)$version->id);
                if ($rows && !$rows->isEmpty()) {
                    $version->pending_operation = (string)$rows->first()->operation;
                }
            }
        }

        $nextVersion = (int)DB::table('product_template_versions')
            ->where('template_id', $id)
            ->whereNull('deleted_at')
            ->max('version') + 1;

        return view('work.templates.edit', [
            'template' => $template,
            'versions' => $versions,
            'nextVersion' => $nextVersion,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $template = DB::table('product_templates')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$template) abort(404);

        $data = $request->validate([
            'template_code' => 'required|string|max:255|unique:product_templates,template_code,' . $id,
            'name' => 'required|string|max:255',
            'memo' => 'nullable|string|max:5000',
        ]);

        $active = $request->boolean('active', false);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;

        $after = [
            'template_code' => $data['template_code'],
            'name' => $data['name'],
            'active' => $active,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueUpdate(
            'product_template',
            $id,
            (array)$template,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.templates.edit', $id)->with('status', 'テンプレの更新申請を送信しました');
    }

    public function destroy(Request $request, int $id)
    {
        $template = DB::table('product_templates')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$template) abort(404);

        app(WorkChangeRequestService::class)->queueDelete(
            'product_template',
            $id,
            (array)$template,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.templates.index')->with('status', 'テンプレの削除申請を送信しました');
    }
}
