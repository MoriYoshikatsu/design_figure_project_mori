<?php

namespace App\Http\Controllers;

use App\Services\WorkChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class LaborCostController extends Controller
{
    public function index(Request $request): View
    {
        $tab = (string)$request->query('tab', 'processes');
        if (!in_array($tab, ['processes', 'settings'], true)) {
            $tab = 'processes';
        }

        $setting = DB::table('labor_cost_settings')->orderBy('id')->first();

        $processRows = DB::table('labor_processes')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $elementRows = DB::table('labor_process_elements')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $elementsByProcessId = $elementRows->groupBy('process_id');

        $processes = [];
        foreach ($processRows as $row) {
            $processes[] = [
                'id' => (int)$row->id,
                'process_code' => (string)$row->process_code,
                'name' => (string)$row->name,
                'default_yield_rate' => $row->default_yield_rate !== null ? (float)$row->default_yield_rate : null,
                'active' => (bool)$row->active,
                'sort_order' => (int)$row->sort_order,
                'memo' => (string)($row->memo ?? ''),
                'elements' => $this->serializeElementRows($elementsByProcessId->get((int)$row->id)?->all() ?? []),
            ];
        }

        $rules = DB::table('labor_auto_rules as r')
            ->join('labor_processes as p', 'p.id', '=', 'r.process_id')
            ->whereNull('r.deleted_at')
            ->select(
                'r.*',
                'p.process_code',
                'p.name as process_name'
            )
            ->orderBy('r.priority')
            ->orderBy('r.id')
            ->get()
            ->map(function (object $row): array {
                return [
                    'id' => (int)$row->id,
                    'rule_code' => (string)$row->rule_code,
                    'name' => (string)$row->name,
                    'process_id' => (int)$row->process_id,
                    'process_code' => (string)$row->process_code,
                    'process_name' => (string)$row->process_name,
                    'priority' => (int)$row->priority,
                    'include_tags' => $this->decodeJsonArray($row->include_tags_json),
                    'exclude_tags' => $this->decodeJsonArray($row->exclude_tags_json),
                    'required_part_codes' => $this->decodeJsonArray($row->required_part_codes_json),
                    'always_apply' => (bool)$row->always_apply,
                    'active' => (bool)$row->active,
                    'memo' => (string)($row->memo ?? ''),
                ];
            })
            ->values()
            ->all();

        $skuOptionsByCategory = [];
        $skuRows = DB::table('parts')
            ->whereNull('deleted_at')
            ->orderBy('category')
            ->orderBy('part_code')
            ->get(['part_code', 'name', 'category', 'active']);
        foreach ($skuRows as $row) {
            $category = strtoupper(trim((string)($row->category ?? '')));
            if ($category === '') {
                $category = 'OTHER';
            }
            $skuOptionsByCategory[$category][] = [
                'part_code' => strtoupper(trim((string)($row->part_code ?? ''))),
                'name' => trim((string)($row->name ?? '')),
                'active' => (bool)($row->active ?? true),
            ];
        }
        ksort($skuOptionsByCategory);

        return view('work.labor-costs.index', [
            'activeTab' => $tab,
            'setting' => $setting,
            'processes' => $processes,
            'rules' => $rules,
            'processOptions' => array_map(
                static fn (array $process): array => [
                    'id' => (int)$process['id'],
                    'label' => (string)$process['name'] . ' (' . (string)$process['process_code'] . ')',
                ],
                $processes
            ),
            'skuOptionsByCategory' => $skuOptionsByCategory,
        ]);
    }

    public function updateSetting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hourly_rate' => 'required|numeric|min:0',
            'memo' => 'nullable|string|max:5000',
        ]);

        $current = DB::table('labor_cost_settings')->orderBy('id')->first();
        $before = $current ? (array)$current : [
            'id' => 1,
            'hourly_rate' => 9000,
            'memo' => null,
        ];
        $after = [
            'id' => (int)($current->id ?? 1),
            'hourly_rate' => (float)$data['hourly_rate'],
            'memo' => $this->normalizeMemo($data['memo'] ?? null),
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueUpdate(
            'labor_cost_setting',
            (int)$after['id'],
            $before,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'settings'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '作業費全体設定を更新しました',
            '作業費全体設定の更新申請を送信しました'
        ));
    }

    public function storeProcess(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'process_code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'default_yield_rate' => 'nullable|numeric|gt:0',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'memo' => 'nullable|string|max:5000',
        ]);

        $after = [
            'process_code' => strtoupper(trim((string)$data['process_code'])),
            'name' => trim((string)$data['name']),
            'default_yield_rate' => is_numeric($data['default_yield_rate'] ?? null) ? (float)$data['default_yield_rate'] : null,
            'active' => $request->boolean('active', true),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'memo' => $this->normalizeMemo($data['memo'] ?? null),
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueCreate(
            'labor_process',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'processes'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '工程を作成しました',
            '工程の作成申請を送信しました'
        ));
    }

    public function updateProcess(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('labor_processes')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }

        $data = $request->validate([
            'process_code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'default_yield_rate' => 'nullable|numeric|gt:0',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'memo' => 'nullable|string|max:5000',
        ]);

        $after = [
            'process_code' => strtoupper(trim((string)$data['process_code'])),
            'name' => trim((string)$data['name']),
            'default_yield_rate' => is_numeric($data['default_yield_rate'] ?? null) ? (float)$data['default_yield_rate'] : null,
            'active' => $request->boolean('active', false),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'memo' => $this->normalizeMemo($data['memo'] ?? null),
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueUpdate(
            'labor_process',
            $id,
            (array)$row,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'processes'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '工程を更新しました',
            '工程の更新申請を送信しました'
        ));
    }

    public function destroyProcess(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('labor_processes')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueDelete(
            'labor_process',
            $id,
            (array)$row,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'processes'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '工程を削除しました',
            '工程の削除申請を送信しました'
        ));
    }

    public function storeElement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'process_id' => 'required|integer|exists:labor_processes,id',
            'element_code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'work_minutes' => 'required|numeric|min:0',
            'activity_coeff' => 'required|numeric|min:0',
            'batch_size' => 'required|integer|min:1',
            'depreciation_amount' => 'required|numeric|min:0',
            'default_yield_rate' => 'nullable|numeric|gt:0',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'memo' => 'nullable|string|max:5000',
        ]);

        $after = [
            'process_id' => (int)$data['process_id'],
            'element_code' => strtoupper(trim((string)$data['element_code'])),
            'name' => trim((string)$data['name']),
            'work_minutes' => (float)$data['work_minutes'],
            'activity_coeff' => (float)$data['activity_coeff'],
            'batch_size' => (int)$data['batch_size'],
            'depreciation_amount' => (float)$data['depreciation_amount'],
            'default_yield_rate' => is_numeric($data['default_yield_rate'] ?? null) ? (float)$data['default_yield_rate'] : null,
            'active' => $request->boolean('active', true),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'memo' => $this->normalizeMemo($data['memo'] ?? null),
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueCreate(
            'labor_process_element',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'processes'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '工程要素を作成しました',
            '工程要素の作成申請を送信しました'
        ));
    }

    public function updateElement(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('labor_process_elements')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }

        $data = $request->validate([
            'process_id' => 'required|integer|exists:labor_processes,id',
            'element_code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'work_minutes' => 'required|numeric|min:0',
            'activity_coeff' => 'required|numeric|min:0',
            'batch_size' => 'required|integer|min:1',
            'depreciation_amount' => 'required|numeric|min:0',
            'default_yield_rate' => 'nullable|numeric|gt:0',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'memo' => 'nullable|string|max:5000',
        ]);

        $after = [
            'process_id' => (int)$data['process_id'],
            'element_code' => strtoupper(trim((string)$data['element_code'])),
            'name' => trim((string)$data['name']),
            'work_minutes' => (float)$data['work_minutes'],
            'activity_coeff' => (float)$data['activity_coeff'],
            'batch_size' => (int)$data['batch_size'],
            'depreciation_amount' => (float)$data['depreciation_amount'],
            'default_yield_rate' => is_numeric($data['default_yield_rate'] ?? null) ? (float)$data['default_yield_rate'] : null,
            'active' => $request->boolean('active', false),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'memo' => $this->normalizeMemo($data['memo'] ?? null),
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueUpdate(
            'labor_process_element',
            $id,
            (array)$row,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'processes'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '工程要素を更新しました',
            '工程要素の更新申請を送信しました'
        ));
    }

    public function destroyElement(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('labor_process_elements')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueDelete(
            'labor_process_element',
            $id,
            (array)$row,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'processes'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '工程要素を削除しました',
            '工程要素の削除申請を送信しました'
        ));
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rule_code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'process_id' => 'required|integer|exists:labor_processes,id',
            'priority' => 'nullable|integer',
            'include_tags' => 'nullable|string|max:5000',
            'exclude_tags' => 'nullable|string|max:5000',
            'required_part_codes' => 'nullable|array',
            'required_part_codes.*' => 'string|max:255',
            'always_apply' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'memo' => 'nullable|string|max:5000',
        ]);

        $after = [
            'rule_code' => strtoupper(trim((string)$data['rule_code'])),
            'name' => trim((string)$data['name']),
            'process_id' => (int)$data['process_id'],
            'priority' => (int)($data['priority'] ?? 100),
            'include_tags_json' => $this->parseCsvList((string)($data['include_tags'] ?? ''), false),
            'exclude_tags_json' => $this->parseCsvList((string)($data['exclude_tags'] ?? ''), false),
            'required_part_categories_json' => [],
            'required_part_codes_json' => $this->normalizeListInput($data['required_part_codes'] ?? [], true),
            'always_apply' => $request->boolean('always_apply', false),
            'active' => $request->boolean('active', true),
            'memo' => $this->normalizeMemo($data['memo'] ?? null),
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueCreate(
            'labor_auto_rule',
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'settings'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '自動ルールを作成しました',
            '自動ルールの作成申請を送信しました'
        ));
    }

    public function updateRule(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('labor_auto_rules')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }

        $data = $request->validate([
            'rule_code' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'process_id' => 'required|integer|exists:labor_processes,id',
            'priority' => 'nullable|integer',
            'include_tags' => 'nullable|string|max:5000',
            'exclude_tags' => 'nullable|string|max:5000',
            'required_part_codes' => 'nullable|array',
            'required_part_codes.*' => 'string|max:255',
            'always_apply' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'memo' => 'nullable|string|max:5000',
        ]);

        $before = $this->serializeRuleRow($row);
        $after = [
            'rule_code' => strtoupper(trim((string)$data['rule_code'])),
            'name' => trim((string)$data['name']),
            'process_id' => (int)$data['process_id'],
            'priority' => (int)($data['priority'] ?? 100),
            'include_tags_json' => $this->parseCsvList((string)($data['include_tags'] ?? ''), false),
            'exclude_tags_json' => $this->parseCsvList((string)($data['exclude_tags'] ?? ''), false),
            'required_part_categories_json' => [],
            'required_part_codes_json' => $this->normalizeListInput($data['required_part_codes'] ?? [], true),
            'always_apply' => $request->boolean('always_apply', false),
            'active' => $request->boolean('active', false),
            'memo' => $this->normalizeMemo($data['memo'] ?? null),
        ];

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueUpdate(
            'labor_auto_rule',
            $id,
            $before,
            $after,
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'settings'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '自動ルールを更新しました',
            '自動ルールの更新申請を送信しました'
        ));
    }

    public function destroyRule(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('labor_auto_rules')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }

        $changeRequestService = app(WorkChangeRequestService::class);
        $submission = $changeRequestService->queueDelete(
            'labor_auto_rule',
            $id,
            $this->serializeRuleRow($row),
            (int)$request->user()->id,
            (string)$request->input('comment', '')
        );

        return redirect()->route('work.labor-costs.index', ['tab' => 'settings'])->with('status', $changeRequestService->outcomeMessage(
            $submission,
            '自動ルールを削除しました',
            '自動ルールの削除申請を送信しました'
        ));
    }

    /**
     * @param array<int, object> $rows
     * @return array<int, array<string, mixed>>
     */
    private function serializeElementRows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int)$row->id,
                'process_id' => (int)$row->process_id,
                'element_code' => (string)$row->element_code,
                'name' => (string)$row->name,
                'work_minutes' => (float)$row->work_minutes,
                'activity_coeff' => (float)$row->activity_coeff,
                'batch_size' => (int)$row->batch_size,
                'depreciation_amount' => (float)$row->depreciation_amount,
                'default_yield_rate' => $row->default_yield_rate !== null ? (float)$row->default_yield_rate : null,
                'active' => (bool)$row->active,
                'sort_order' => (int)$row->sort_order,
                'memo' => (string)($row->memo ?? ''),
            ];
        }
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRuleRow(object $row): array
    {
        return [
            'id' => (int)$row->id,
            'rule_code' => (string)$row->rule_code,
            'name' => (string)$row->name,
            'process_id' => (int)$row->process_id,
            'priority' => (int)$row->priority,
            'include_tags_json' => $this->decodeJsonArray($row->include_tags_json),
            'exclude_tags_json' => $this->decodeJsonArray($row->exclude_tags_json),
            'required_part_categories_json' => $this->decodeJsonArray($row->required_part_categories_json),
            'required_part_codes_json' => $this->decodeJsonArray($row->required_part_codes_json),
            'always_apply' => (bool)$row->always_apply,
            'active' => (bool)$row->active,
            'memo' => (string)($row->memo ?? ''),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseCsvList(string $raw, bool $uppercase): array
    {
        $result = [];
        foreach (explode(',', $raw) as $item) {
            $value = trim($item);
            if ($value === '') {
                continue;
            }
            $value = $uppercase ? strtoupper($value) : strtolower($value);
            $result[$value] = true;
        }
        return array_keys($result);
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizeListInput(mixed $raw, bool $uppercase): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            $value = trim((string)$item);
            if ($value === '') {
                continue;
            }
            $value = $uppercase ? strtoupper($value) : strtolower($value);
            $result[$value] = true;
        }

        return array_keys($result);
    }

    /**
     * @return array<int, string>
     */
    private function decodeJsonArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map(static fn ($v): string => (string)$v, $raw));
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_map(static fn ($v): string => (string)$v, $decoded));
    }

    private function normalizeMemo(?string $memo): ?string
    {
        $value = trim((string)$memo);
        return $value === '' ? null : $value;
    }
}
