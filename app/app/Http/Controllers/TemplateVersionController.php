<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\WorkChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TemplateVersionController extends Controller
{
    private const FIXED_DSL_VERSION = '0.2';

    public function store(Request $request, int $templateId)
    {
        $template = DB::table('product_templates')->whereNull('deleted_at')->where('id', $templateId)->first();
        if (!$template) abort(404);

        $data = $request->validate([
            'version' => 'required|integer|min:1',
            'dsl_json' => 'required|string',
            'memo' => 'nullable|string|max:5000',
        ]);

        $exists = DB::table('product_template_versions')
            ->where('template_id', $templateId)
            ->whereNull('deleted_at')
            ->where('version', $data['version'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['version' => '同じversionが既に存在します'])->withInput();
        }

        $decoded = json_decode($data['dsl_json'], true);
        if (!is_array($decoded)) {
            return back()->withErrors(['dsl_json' => 'dsl_jsonはJSON形式で入力してください'])->withInput();
        }

        $active = $request->boolean('active', true);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;
        $dslVersion = self::FIXED_DSL_VERSION;

        $after = [
            'template_id' => $templateId,
            'version' => $data['version'],
            'dsl_version' => $dslVersion,
            'dsl_json' => $decoded,
            'active' => $active,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueCreate(
            'product_template_version',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.templates.edit', $templateId)->with('status', 'テンプレversionの作成申請を送信しました');
    }

    public function edit(int $templateId, int $versionId)
    {
        $template = DB::table('product_templates')->whereNull('deleted_at')->where('id', $templateId)->first();
        if (!$template) abort(404);

        $version = DB::table('product_template_versions')
            ->whereNull('deleted_at')
            ->where('id', $versionId)
            ->where('template_id', $templateId)
            ->first();
        if (!$version) abort(404);

        $dsl = $version->dsl_json ?? '';
        if (is_array($dsl)) {
            $dsl = json_encode($dsl, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return view('work.templates.versions.edit', [
            'template' => $template,
            'version' => $version,
            'dslJson' => (string)$dsl,
        ]);
    }

    public function update(Request $request, int $templateId, int $versionId)
    {
        $version = DB::table('product_template_versions')
            ->whereNull('deleted_at')
            ->where('id', $versionId)
            ->where('template_id', $templateId)
            ->first();
        if (!$version) abort(404);

        $data = $request->validate([
            'version' => 'required|integer|min:1',
            'dsl_json' => 'required|string',
            'memo' => 'nullable|string|max:5000',
        ]);

        $exists = DB::table('product_template_versions')
            ->where('template_id', $templateId)
            ->whereNull('deleted_at')
            ->where('version', $data['version'])
            ->where('id', '!=', $versionId)
            ->exists();
        if ($exists) {
            return back()->withErrors(['version' => '同じversionが既に存在します'])->withInput();
        }

        $decoded = json_decode($data['dsl_json'], true);
        if (!is_array($decoded)) {
            return back()->withErrors(['dsl_json' => 'dsl_jsonはJSON形式で入力してください'])->withInput();
        }

        $active = $request->boolean('active', false);
        $memo = trim((string)($data['memo'] ?? ''));
        if ($memo === '') $memo = null;
        $dslVersion = trim((string)($version->dsl_version ?? ''));
        if ($dslVersion === '') {
            $dslVersion = self::FIXED_DSL_VERSION;
        }

        $after = [
            'version' => $data['version'],
            'dsl_version' => $dslVersion,
            'dsl_json' => $decoded,
            'active' => $active,
            'memo' => $memo,
        ];

        app(WorkChangeRequestService::class)->queueUpdate(
            'product_template_version',
            $versionId,
            (array)$version,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.templates.edit', $templateId)->with('status', 'テンプレversionの更新申請を送信しました');
    }

    public function destroy(Request $request, int $templateId, int $versionId)
    {
        $version = DB::table('product_template_versions')
            ->whereNull('deleted_at')
            ->where('id', $versionId)
            ->where('template_id', $templateId)
            ->first();
        if (!$version) abort(404);

        app(WorkChangeRequestService::class)->queueDelete(
            'product_template_version',
            $versionId,
            (array)$version,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.templates.edit', $templateId)->with('status', 'テンプレversionの削除申請を送信しました');
    }
}
