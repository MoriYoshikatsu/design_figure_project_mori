<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\ConfiguratorSession;
use App\Services\WorkChangeRequestService;
use Illuminate\Support\Facades\Cookie;
use Throwable;

final class Configurator extends Component
{
    /** @var array<string, string> */
    private const SUMMARY_FIELD_LABELS = [
        'quote_id' => '見積ID',
        'status' => 'ステータス',
        'account_internal_name' => 'accounts.internal_name',
        'account_user_name' => 'users.name',
        'assignee_name' => '担当者',
        'customer_emails' => '登録メールアドレス',
        'request_count' => '承認リクエスト件数',
        'template_version_id' => 'ルールテンプレ',
        'price_book_id' => '納品物価格表',
        'subtotal' => '小計',
        'tax' => '税',
        'total' => '合計',
    ];
    /** @var array<int, string> */
    private const SUMMARY_DEFAULT_FIELDS = [
        'quote_id',
        'status',
        'account_internal_name',
        'account_user_name',
        'assignee_name',
        'customer_emails',
        'request_count',
        'template_version_id',
        'price_book_id',
        'subtotal',
        'tax',
        'total',
    ];

    public array $config = [];
    public array $derived = [];
    public array $errors = [];
    public string $svg = '';
    public string $svgDataUrl = '';
    public ?int $sessionId = null; // DBのconfigurator_sessions.id
    public array $skuOptions = [];
    public array $skuNameMap = [];
    public array $skuSvgMap = [];
    public array $templateVersionOptions = [];
    public ?int $templateVersionId = null;
    public array $templateDsl = [];
    public ?int $quoteEditId = null;
    public ?int $quoteAccountId = null;
    public ?array $initialConfig = null;
    public ?int $initialTemplateVersionId = null;
    public ?string $initialMemo = null;
    public ?array $initialSummaryFields = null;
    public ?array $initialSummaryFieldOptions = null;
    public array $summaryFields = [];
    public array $summaryFieldOptions = [];
    public ?string $memo = null;
    public bool $isSaving = false;      // 保存中フラグ
    public ?string $saveError = null;   // 保存失敗メッセージ（なければnull）
    public bool $dirty = false;       // 未保存フラグ
    public string $saveStatus = '';   // 表示用（保存した時刻など）
    private float $lastSavedAt = 0.0;       // 最終保存時刻（秒）
    private float $saveIntervalSec = 1.0;   // 保存間隔（秒）

    public function mount(
        ?int $quoteEditId = null,
        ?int $quoteAccountId = null,
        ?array $initialConfig = null,
        ?int $initialTemplateVersionId = null,
        ?string $initialMemo = null,
        ?array $initialSummaryFields = null,
        ?array $summaryFieldOptions = null
    ): void
    {
        if ($quoteEditId) {
            $this->quoteEditId = $quoteEditId;
        }
        if ($quoteAccountId) {
            $this->quoteAccountId = $quoteAccountId;
        }
        if (is_array($initialConfig)) {
            $this->initialConfig = $initialConfig;
        }
        if ($initialTemplateVersionId) {
            $this->initialTemplateVersionId = $initialTemplateVersionId;
        }
        if ($initialMemo !== null) {
            $this->initialMemo = $initialMemo;
        }
        if (is_array($initialSummaryFields)) {
            $this->initialSummaryFields = $initialSummaryFields;
        }
        if (is_array($summaryFieldOptions)) {
            $this->initialSummaryFieldOptions = $summaryFieldOptions;
        }

        $this->summaryFieldOptions = is_array($this->initialSummaryFieldOptions) && !empty($this->initialSummaryFieldOptions)
            ? $this->initialSummaryFieldOptions
            : self::SUMMARY_FIELD_LABELS;
        $this->summaryFields = $this->normalizeSummaryFields(
            is_array($this->initialSummaryFields) ? $this->initialSummaryFields : self::SUMMARY_DEFAULT_FIELDS
        );

        // 見積編集モードでは configurator_sessions を新規作成しない
        if ($this->quoteEditId && is_array($this->initialConfig)) {
            $this->sessionId = null;
            $this->templateVersionOptions = $this->buildTemplateVersionOptions();
            $templateVersionId = $this->initialTemplateVersionId ?: $this->ensureTemplateVersionId();
            $this->templateVersionId = (int)$templateVersionId;
            $this->templateDsl = $this->loadTemplateDsl((int)$templateVersionId) ?? [];
            $this->config = $this->initialConfig;
            $this->normalizeConfigUnitsToM();
            $this->derived = [];
            $this->errors = [];
            $this->memo = $this->normalizeMemo($this->initialMemo);
            $this->skuOptions = $this->buildSkuOptions();
            $this->skuNameMap = $this->buildSkuNameMap();
            $this->skuSvgMap = $this->buildSkuSvgMap();
            $this->dirty = false;
            $this->saveError = null;
            $this->saveStatus = '';
            $this->recompute(true);
            return;
        }

        $cookieName = 'config_session_id';
        $session = null;

        if (is_array($this->initialConfig)) {
            $templateVersionId = $this->initialTemplateVersionId ?: $this->ensureTemplateVersionId();
            $session = ConfiguratorSession::create([
                'account_id' => $this->resolveAccountId(),
                'template_version_id' => $templateVersionId,
                'status' => 'DRAFT',
                'config' => $this->initialConfig,
                'derived' => [],
                'validation_errors' => [],
                'memo' => $this->normalizeMemo($this->initialMemo),
            ]);

            Cookie::queue(
                Cookie::make($cookieName, (string)$session->id, 60 * 24 * 30)
                    ->withHttpOnly(true)
                    ->withSameSite('Lax')
            );
        } else {
            $sid = request()->cookie($cookieName);
            if (is_numeric($sid)) {
                $candidate = ConfiguratorSession::find((int)$sid);
                if ($candidate) {
                    $userId = (int)auth()->id();
                    if ($userId > 0) {
                        if ($this->sessionBelongsToUser($candidate, $userId)) {
                            $session = $candidate;
                        } elseif ($this->sessionIsUnclaimed($candidate)) {
                            // ゲスト状態で作成したセッションを、直後ログインしたユーザーへ引き継ぐ
                            $candidate->account_id = $this->resolveAccountId();
                            $candidate->updated_at = now();
                            $candidate->save();
                            $session = $candidate->fresh();
                        }
                    } else {
                        // ゲストは未紐付けaccountのセッションのみ扱う
                        if ($this->sessionIsUnclaimed($candidate)) {
                            $session = $candidate;
                        }
                    }
                }
            }

            if (!$session) {
                // 新規作成（ログインユーザーの account_id と選択テンプレを反映）
                $templateVersionId = $this->ensureTemplateVersionId();
                $config = $this->loadTemplateConfig($templateVersionId) ?? $this->defaultConfig();
                $session = ConfiguratorSession::create([
                    'account_id' => $this->resolveAccountId(),
                    'template_version_id' => $templateVersionId,
                    'status' => 'DRAFT',
                    'config' => $config,
                    'derived' => [],
                    'validation_errors' => [],
                ]);

                // CookieにセッションIDを保存（30日）
                Cookie::queue(
                    Cookie::make($cookieName, (string)$session->id, 60 * 24 * 30) // 分（minutes：分）
                        ->withHttpOnly(true)      // HttpOnly（JSから読めない）
                        ->withSameSite('Lax')     // SameSite（別サイトからの送信制限）
                );
            }
        }

        $this->sessionId = $session->id;
        $this->templateVersionOptions = $this->buildTemplateVersionOptions();
        $this->templateVersionId = (int)$session->template_version_id;
        $this->templateDsl = $this->loadTemplateDsl($this->templateVersionId) ?? [];
        $this->config = $session->config ?? $this->defaultConfig();
        $this->normalizeConfigUnitsToM();
        $this->derived = $session->derived ?? [];
        $this->errors = $session->validation_errors ?? [];
        $this->memo = $session->memo;
        $this->skuOptions = $this->buildSkuOptions();
        $this->skuNameMap = $this->buildSkuNameMap();
        $this->skuSvgMap = $this->buildSkuSvgMap();

        $this->recompute(true); // SVGも更新
    }

    public function updatedTemplateVersionId(mixed $value): void
    {
        if (!$this->sessionId) return;

        $id = is_numeric($value) ? (int)$value : null;
        if (!$id) return;

        $config = $this->loadTemplateConfig($id);
        if (is_array($config)) {
            $this->config = $config;
        }
        $this->templateDsl = $this->loadTemplateDsl($id) ?? [];

        $this->derived = [];
        $this->errors = [];
        $this->recompute(true);

        $ok = $this->persistToDb(['template_version_id' => $id]);
        if ($ok) {
            $this->saveStatus = 'テンプレ反映(TOKYO): ' . now()->format('H:i:s');
        }
    }

    private function defaultConfig(): array
    {
        return [
            'mfdCount' => 2,
            'sleeves' => [
                ['skuCode' => 'SLEEVE_RECOTE'],
                ['skuCode' => 'SLEEVE_RECOTE'],
            ],
            'fibers' => [
                ['skuCode' => 'FIBER_SMF28', 'lengthM' => 0.5, 'toleranceM' => 0.005, 'toleranceAuto' => true],
                ['skuCode' => 'FIBER_SMF28', 'lengthM' => 0.3, 'toleranceM' => 0.003, 'toleranceAuto' => true],
                ['skuCode' => 'FIBER_SMF28', 'lengthM' => 0.5, 'toleranceM' => 0.005, 'toleranceAuto' => true],
            ],
            'tubeCount' => 1,
            'tubes' => [
                [
                    'skuCode' => 'TUBE_0.9_LOOSE',
                    'anchor' => ['type' => 'MFD', 'index' => 0],
                    'startFiberIndex' => 0,
                    'endFiberIndex' => 0,
                    'startOffsetM' => 0,
                    'endOffsetM' => 0.2,
                    'toleranceM' => null,
                    'toleranceAuto' => true,
                ],
            ],
            'connectors' => [
                'mode' => 'both',
                'leftSkuCode' => 'CONN_SC_PC',
                'rightSkuCode' => null,
            ],
        ];
    }

    private function buildSkuOptions(): array
    {
        $rows = DB::table('skus')
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('sku_code')
            ->get(['sku_code', 'name', 'category']);

        $byCategory = [
            'SLEEVE' => [],
            'FIBER' => [],
            'TUBE' => [],
            'CONNECTOR' => [],
        ];

        foreach ($rows as $r) {
            $cat = strtoupper((string)($r->category ?? ''));
            if (!array_key_exists($cat, $byCategory)) {
                continue;
            }
            $byCategory[$cat][] = [
                'code' => (string)$r->sku_code,
                'label' => (string)$r->name,
            ];
        }

        return [
            'sleeve' => $byCategory['SLEEVE'],
            'fiber' => $byCategory['FIBER'],
            'tube' => $byCategory['TUBE'],
            'connector' => $byCategory['CONNECTOR'],
        ];
    }

    private function buildSkuNameMap(): array
    {
        $rows = DB::table('skus')
            ->where('active', true)
            ->get(['sku_code', 'name']);

        $map = [];
        foreach ($rows as $r) {
            $code = (string)$r->sku_code;
            if ($code === '') continue;
            $map[$code] = (string)$r->name;
        }

        return $map;
    }

    private function buildSkuSvgMap(): array
    {
        $rows = DB::table('skus')
            ->where('active', true)
            ->get(['sku_code']);

        $map = [];
        foreach ($rows as $r) {
            $code = (string)$r->sku_code;
            if ($code === '') continue;
            $rel = 'sku-svg/' . $code . '.svg';
            $abs = public_path($rel);
            if (is_file($abs)) {
                $map[$code] = '/' . $rel;
            }
        }

        return $map;
    }

    private function resolveAccountId(): int
    {
        $user = auth()->user();
        if ($user) {
            $userId = (int)$user->id;
            $accountId = (int)DB::table('account_user')
                ->where('user_id', $userId)
                ->orderByRaw("
                    case role
                        when 'customer' then 1
                        when 'admin' then 2
                        when 'sales' then 3
                        else 9
                    end
                ")
                ->orderBy('account_id')
                ->value('account_id');
            if ($accountId > 0) return $accountId;

            return (int)DB::transaction(function () use ($userId, $user) {
                $accountId = (int)DB::table('accounts')->insertGetId([
                    'account_type' => 'B2C',
                    'internal_name' => trim((string)$user->name) !== '' ? (string)$user->name : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('account_user')->insert([
                    'account_id' => $accountId,
                    'user_id' => $userId,
                    'role' => 'customer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $accountId;
            });
        }

        if ($this->sessionId) {
            $sessionAccountId = (int)DB::table('configurator_sessions')
                ->where('id', (int)$this->sessionId)
                ->value('account_id');
            if ($sessionAccountId > 0) {
                return $sessionAccountId;
            }
        }

        // 未ログイン時は未紐付けの一時accountを都度作成（固定accountへの誤紐付けを防止）
        return (int)DB::table('accounts')->insertGetId([
            'account_type' => 'B2C',
            'internal_name' => null,
            'memo' => 'GUEST_TEMP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sessionBelongsToUser(ConfiguratorSession $session, int $userId): bool
    {
        if ($userId <= 0) return false;

        return DB::table('account_user')
            ->where('account_id', (int)$session->account_id)
            ->where('user_id', $userId)
            ->exists();
    }

    private function sessionIsUnclaimed(ConfiguratorSession $session): bool
    {
        return !DB::table('account_user')
            ->where('account_id', (int)$session->account_id)
            ->exists();
    }

    private function ensureTemplateVersionId(): int
    {
        $id = (int)DB::table('product_template_versions as v')
            ->join('product_templates as t', 't.id', '=', 'v.template_id')
            ->where('t.active', true)
            ->where('v.active', true)
            ->orderBy('t.name')
            ->orderBy('v.version')
            ->value('v.id');
        if ($id > 0) return $id;

        $templateId = (int)DB::table('product_templates')->insertGetId([
            'template_code' => 'TPL_AUTO',
            'name' => 'Auto Template',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int)DB::table('product_template_versions')->insertGetId([
            'template_id' => $templateId,
            'version' => 1,
            'dsl_version' => '0.2',
            'dsl_json' => json_encode(['template_code' => 'TPL_AUTO'], JSON_UNESCAPED_UNICODE),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildTemplateVersionOptions(): array
    {
        $rows = DB::table('product_template_versions as v')
            ->join('product_templates as t', 't.id', '=', 'v.template_id')
            ->where('t.active', true)
            ->where('v.active', true)
            ->orderBy('t.name')
            ->orderBy('v.version')
            ->get([
                'v.id',
                'v.version',
                'v.dsl_version',
                't.name as template_name',
                't.template_code',
            ]);

        $options = [];
        foreach ($rows as $r) {
            $label = sprintf(
                '%s (%s) v%s',
                (string)$r->template_name,
                (string)$r->template_code,
                (string)$r->version
            );
            $options[] = [
                'id' => (int)$r->id,
                'label' => $label,
            ];
        }

        return $options;
    }

    private function loadTemplateDsl(int $templateVersionId): ?array
    {
        $raw = DB::table('product_template_versions')
            ->where('id', $templateVersionId)
            ->value('dsl_json');
        if ($raw === null) return null;

        $dsl = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($dsl)) return null;

        return $dsl;
    }

    private function loadTemplateConfig(int $templateVersionId): ?array
    {
        $dsl = $this->loadTemplateDsl($templateVersionId);
        if (!is_array($dsl)) return null;

        $defaults = $dsl['default_config'] ?? $dsl['config'] ?? null;
        if (!is_array($defaults)) return null;

        return array_replace_recursive($this->defaultConfig(), $defaults);
    }

    private function persistToDb(array $extra = []): bool
    {
        if (!$this->sessionId) return false;

        $this->isSaving = true;
        $this->saveError = null;

        try {
            $payload = [
                'config' => $this->config,
                'derived' => $this->derived,
                'validation_errors' => $this->errors,
                'memo' => $this->normalizeMemo($this->memo),
                'status' => 'DRAFT',
            ];
            if (!empty($extra)) {
                $payload = array_merge($payload, $extra);
            }
            ConfiguratorSession::where('id', $this->sessionId)->update($payload);

            $this->dirty = false;
            $this->lastSavedAt = microtime(true);
            return true;

        } catch (Throwable $e) {
            // ログ（log：記録）に残す
            report($e);

            // 画面には簡潔に（DBエラー詳細をそのまま出さない）
            $this->saveError = '保存に失敗しました。通信状態やDB状態を確認してください。';
            // 失敗したので未保存のまま
            $this->dirty = true;
            return false;

        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * Livewireがどのプロパティでも更新したら呼ばれる（汎用フック）
     * $name: 更新されたプロパティ名（例: config.mfdCount）
     */
    public function updated(string $name, mixed $value): void
    {
        $this->dirty = true;

        if ($name === 'memo') {
            if (!$this->quoteEditId) {
                $this->autoSaveIfDue();
            }
            return;
        }

        if (!str_starts_with($name, 'config.')) return;

        if (str_contains($name, '.toleranceM')) {
            $this->markToleranceAutoByPath($name, $value);
        }

        $resizeArrays = in_array($name, ['config.mfdCount', 'config.tubeCount'], true);
        $this->recompute($resizeArrays);

        // ついでに「一定間隔で自動保存」もここで（次章）
        if (!$this->quoteEditId) {
            $this->autoSaveIfDue();
        }
    }

    public function saveNow(): void
    {
        // recompute（再計算）直後を保存したいので、念のため再計算するならここで呼ぶ
        // $this->recompute(app(SvgRenderer::class)); // 必要なら

        $ok = $this->persistToDb();
        if ($ok) {
            $this->saveStatus = '手動保存(TOKYO): ' . now()->format('H:i:s');
            $this->dispatch('saved'); // 任意：トースト（toast：小通知）用
        } else {
            $this->saveStatus = '手動保存失敗(TOKYO): ' . now()->format('H:i:s');
            $this->dispatch('save-failed');
        }
    }

    public function issueQuote(): mixed
    {
        if (!$this->sessionId) return null;

        $ok = $this->persistToDb();
        if (!$ok) {
            $this->saveStatus = '見積発行失敗(TOKYO): ' . now()->format('H:i:s');
            return null;
        }

        /** @var \App\Services\QuoteService $quoteService */
        $quoteService = app(\App\Services\QuoteService::class);
        try {
            $quoteId = $quoteService->createFromSession($this->sessionId, auth()->id(), false);
        } catch (\Throwable $e) {
            report($e);
            $this->saveError = '見積発行に失敗しました。ログイン状態とアカウント紐付けを確認してください。';
            $this->saveStatus = '見積発行失敗(TOKYO): ' . now()->format('H:i:s');
            return null;
        }

        return redirect()->to('/quotes/' . $quoteId);
    }

    public function requestQuoteEdit(): void
    {
        if (!$this->quoteEditId) return;

        $this->recompute(true);

        $dsl = $this->templateDsl;
        /** @var \App\Services\DslEngine $engine */
        $engine = app(\App\Services\DslEngine::class);
        $eval = $engine->evaluate($this->config, is_array($dsl) ? $dsl : []);
        $derived = is_array($eval['derived'] ?? null) ? $eval['derived'] : [];
        $errors = is_array($eval['errors'] ?? null) ? $eval['errors'] : [];

        /** @var \App\Services\BomBuilder $bomBuilder */
        $bomBuilder = app(\App\Services\BomBuilder::class);
        $bom = $bomBuilder->build($this->config, $derived, $dsl);

        $accountId = (int)DB::table('quotes')->where('id', $this->quoteEditId)->value('account_id');
        /** @var \App\Services\PricingService $pricing */
        $pricing = app(\App\Services\PricingService::class);
        $pricingResult = $pricing->price($accountId, $bom);

        $snapshot = [
            'template_version_id' => (int)$this->templateVersionId,
            'price_book_id' => $pricingResult['price_book_id'] ?? null,
            'config' => $this->config,
            'derived' => $derived,
            'validation_errors' => $errors,
            'memo' => $this->normalizeMemo($this->memo),
            'bom' => $bom,
            'pricing' => $pricingResult['items'] ?? [],
            'totals' => [
                'subtotal' => (float)($pricingResult['subtotal'] ?? 0),
                'tax' => (float)($pricingResult['tax'] ?? 0),
                'total' => (float)($pricingResult['total'] ?? 0),
            ],
        ];

        $quoteRow = DB::table('quotes')
            ->where('id', $this->quoteEditId)
            ->first(['snapshot', 'memo']);
        $baseSnapshot = is_array($quoteRow?->snapshot) ? $quoteRow?->snapshot : json_decode((string)($quoteRow?->snapshot ?? ''), true);
        if (!is_array($baseSnapshot)) $baseSnapshot = [];
        $summaryFields = $this->normalizeSummaryFields($this->summaryFields);
        $this->summaryFields = $summaryFields;
        $baseSummaryFields = $this->normalizeSummaryFields(
            is_array($baseSnapshot['summary_card_fields'] ?? null)
                ? $baseSnapshot['summary_card_fields']
                : self::SUMMARY_DEFAULT_FIELDS
        );

        $snapshot['summary_card_fields'] = $summaryFields;
        $baseSnapshot['summary_card_fields'] = $baseSummaryFields;
        unset($snapshot['account_display_name_source'], $baseSnapshot['account_display_name_source']);
        $baseSnapshot['memo'] = $this->normalizeMemo((string)($quoteRow?->memo ?? ''));

        app(WorkChangeRequestService::class)->queueUpdate(
            'quote',
            (int)$this->quoteEditId,
            [
                'snapshot' => $baseSnapshot,
                'memo' => $quoteRow?->memo,
            ],
            [
                'snapshot' => $snapshot,
            ],
            (int)auth()->id(),
            'Configuratorからの変更申請'
        );

        $this->saveStatus = '見積変更申請(TOKYO): ' . now()->format('H:i:s');
    }

    private function autoSaveIfDue(): void
    {
        if (!$this->dirty) return;

        $now = microtime(true);
        if (($now - $this->lastSavedAt) < $this->saveIntervalSec) {
            return;
        }

        $ok = $this->persistToDb();
        if ($ok) {
            $this->saveStatus = '自動保存(TOKYO): ' . now()->format('H:i:s');
        } else {
            $this->saveStatus = '自動保存失敗(TOKYO): ' . now()->format('H:i:s');
        }
    }

    private function recompute(bool $resizeArrays): void
    {
        $this->normalizeConfigUnitsToM();

        // 1) derived（導出）
        $mfdCount = (int)($this->config['mfdCount'] ?? 1);
        $mfdCount = max(1, min(10, $mfdCount));      // 1..10 に丸める
        $this->config['mfdCount'] = $mfdCount;

        $fiberCount = $mfdCount + 1;

        // 2) counts変更時のみ arrays（配列）調整
        if ($resizeArrays) {
            // sleeves（MFD点ごと）
            $this->ensureArraySize('sleeves', $mfdCount, ['skuCode'=>null]);

            $this->ensureArraySize('fibers', $fiberCount, ['skuCode'=>null,'lengthM'=>null,'toleranceM'=>null,'toleranceAuto'=>true]);

            $tubeCount = (int)($this->config['tubeCount'] ?? 0);
            $tubeCount = max(0, min($tubeCount, $fiberCount));   // 0..fiberCount
            $this->config['tubeCount'] = $tubeCount;

            $this->ensureArraySize('tubes', $tubeCount, [
                'skuCode'=>null,
                'anchor'=>['type'=>'MFD','index'=>0],
                'targetFiberIndex'=>0,
                'startFiberIndex'=>0,
                'endFiberIndex'=>0,
                'startOffsetM'=>0,
                'endOffsetM'=>null,
                'lengthM'=>null,
                'toleranceM'=>null,
                'toleranceAuto'=>true,
            ]);
        }

        // 2.4) 旧フィールド互換（sleeveSkuCode → sleeves）
        if (empty($this->config['sleeves']) && !empty($this->config['sleeveSkuCode'])) {
            $code = (string)$this->config['sleeveSkuCode'];
            $this->config['sleeves'] = array_fill(0, $mfdCount, ['skuCode' => $code]);
        }

        // 2.5) ±誤差の自動算出（未入力なら自動埋め）
        $this->applyToleranceDefaultsToFibers();
        $this->applyToleranceDefaultsToTubes();

        // 2.5.1) 旧フィールド互換（targetFiberIndex/lengthM → start/end）
        $this->migrateTubeStartEndFields();

        // 2.6) totalLengthM を計算（未入力は暫定0.1mで計算）
        //      長すぎる区間は「表示用に上限を設ける」 + 挿絵で示す
        $fallbackPerSeg = 0.1;
        $segmentCapM = 1.2; // 要件に合わせて調整
        $displayLens = [];
        $segmentIllustrations = [];

        foreach (($this->config['fibers'] ?? []) as $i => $f) {
            $len = $f['lengthM'] ?? null;
            $actual = (is_numeric($len) && (float)$len > 0) ? (float)$len : $fallbackPerSeg;
            $display = min($actual, $segmentCapM);
            $displayLens[$i] = $display;

            if ($actual > $segmentCapM) {
                $segmentIllustrations[$i] = $this->makeSegmentIllustrationDataUrl($actual, $segmentCapM);
            }
        }

        $dsl = $this->templateDsl;
        /** @var \App\Services\DslEngine $engine */
        $engine = app(\App\Services\DslEngine::class);
        $result = $engine->evaluate($this->config, is_array($dsl) ? $dsl : []);
        $derived = is_array($result['derived'] ?? null) ? $result['derived'] : [];
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];

        $this->derived = array_merge($derived, [
            'displaySegmentLens' => $displayLens,
            'segmentIllustrations' => $segmentIllustrations,
            'segmentLengthCapM' => $segmentCapM,
            'totalLengthM' => array_sum($displayLens),
            'skuNameByCode' => $this->skuNameMap,
            'skuSvgByCode' => $this->skuSvgMap,
        ]);

        // 3) validate（入力検証）→ errors（path付き）
        $this->errors = $errors;

        /** @var \App\Services\BomBuilder $bomBuilder */
        $bomBuilder = app(\App\Services\BomBuilder::class);
        $this->derived['bom'] = $bomBuilder->build($this->config, $this->derived, $dsl);

        /** @var \App\Services\PricingService $pricing */
        $pricing = app(\App\Services\PricingService::class);
        $pricingAccountId = ($this->quoteEditId && $this->quoteAccountId)
            ? (int)$this->quoteAccountId
            : $this->resolveAccountId();
        $this->derived['pricing'] = $pricing->price($pricingAccountId, $this->derived['bom'] ?? []);

        // 4) SVG生成（DIせず app() で解決するのが安定）
        /** @var SvgRenderer $renderer */
        $renderer = app(\App\Services\SvgRenderer::class);
        $svgString = $renderer->render($this->config, $this->derived, $this->errors);

        // SVGを data URL に変換（ブラウザに画像として渡す）
        $this->svgDataUrl = 'data:image/svg+xml;utf8,' . rawurlencode($svgString);
        $this->svg = $svgString;

        // 5) DBへ保存（Cookieのsession_idの行を更新）
        // $now = microtime(true);
        // $shouldSave = ($now - $this->lastSavedAt) >= $this->saveIntervalSec;

        // if ($this->sessionId && $shouldSave) {
        //     ConfiguratorSession::where('id', $this->sessionId)->update([
        //         'config' => $this->config,
        //         'derived' => $this->derived,
        //         'validation_errors' => $this->errors,
        //         'status' => 'DRAFT',
        //     ]);
        //     $this->lastSavedAt = $now;
        // }
    }

    private function ensureArraySize(string $key, int $size, array $fill): void
    {
        $arr = $this->config[$key] ?? [];
        if (!is_array($arr)) $arr = [];

        // 既存行に key が無ければ付ける（過去データ救済）
        foreach ($arr as $idx => $row) {
            if (is_array($row) && empty($row['key'])) {
                $arr[$idx]['key'] = (string) Str::uuid();
            }
        }

        $current = count($arr);
        if ($current < $size) {
            for ($i = $current; $i < $size; $i++) {
                $row = $fill;
                $row['key'] = (string) Str::uuid(); // ★ここが重要
                $arr[] = $row;
            }
        } elseif ($current > $size) {
            $arr = array_slice($arr, 0, $size);
        }

        $this->config[$key] = $arr;
    }

    private function autoTolerance(
        ?float $lengthM,
        float $rate = 0.01,
        float $minDefault = 0.001,
        float $min = 0.0,
        float $max = 0.02
    ): ?float
    {
        if ($lengthM === null || $lengthM <= 0) return null;

        $v = $lengthM * $rate;
        if ($v < $minDefault) $v = $minDefault;
        if ($v < $min) $v = $min;
        if ($v > $max) $v = $max;
        return round($v, 6);
    }

    private function makeSegmentIllustrationDataUrl(float $actualM, float $capM): string
    {
        $label = 'LONG > ' . rtrim(rtrim(number_format($capM, 3, '.', ''), '0'), '.') . 'm';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="22" viewBox="0 0 180 22">'
            . '<rect x="0" y="0" width="180" height="22" rx="3" fill="#fde68a" stroke="#92400e" stroke-width="1"/>'
            . '<path d="M6 11 H20 M26 11 H40 M46 11 H60 M66 11 H80 M86 11 H100 M106 11 H120 M126 11 H140 M146 11 H160 M166 11 H174" stroke="#92400e" stroke-width="2"/>'
            . '<text x="90" y="15" font-size="10" text-anchor="middle" fill="#7c2d12" font-family="ui-sans-serif,system-ui">'
            . $label
            . '</text>'
            . '</svg>';

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    private function normalizeMemo(?string $memo): ?string
    {
        $v = trim((string)$memo);
        return $v === '' ? null : $v;
    }

    private function applyToleranceDefaultsToFibers(): void
    {
        $fibers = $this->config['fibers'] ?? [];
        if (!is_array($fibers)) return;

        foreach ($fibers as $i => $f) {
            $tol = $f['toleranceM'] ?? null;
            $auto = $f['toleranceAuto'] ?? true;

            // 手入力で固定されていないものだけ自動更新
            if ($auto === true) {
                $len = $f['lengthM'] ?? null;
                $len = is_numeric($len) ? (float)$len : null;

                $computed = $this->autoTolerance($len);
                if ($computed !== null) {
                    $fibers[$i]['toleranceM'] = $computed;
                    $fibers[$i]['toleranceAuto'] = true;
                }
            }
        }

        $this->config['fibers'] = $fibers;
    }

    private function applyToleranceDefaultsToTubes(): void
    {
        $tubes = $this->config['tubes'] ?? [];
        if (!is_array($tubes)) return;

        foreach ($tubes as $j => $t) {
            $tol = $t['toleranceM'] ?? null;
            $auto = $t['toleranceAuto'] ?? true;

            if ($auto === true) {
                $len = $t['lengthM'] ?? null;
                $len = is_numeric($len) ? (float)$len : null;

                $computed = $this->autoTolerance($len);
                if ($computed !== null) {
                    $tubes[$j]['toleranceM'] = $computed;
                    $tubes[$j]['toleranceAuto'] = true;
                }
            }
        }

        $this->config['tubes'] = $tubes;
    }

    private function migrateTubeStartEndFields(): void
    {
        $tubes = $this->config['tubes'] ?? [];
        if (!is_array($tubes)) return;

        foreach ($tubes as $j => $t) {
            if (!is_array($t)) continue;

            $hasStart = array_key_exists('startFiberIndex', $t) || array_key_exists('endFiberIndex', $t) || array_key_exists('endOffsetM', $t);
            if ($hasStart) continue;

            $target = $t['targetFiberIndex'] ?? 0;
            $startOffset = $t['startOffsetM'] ?? 0;
            $len = $t['lengthM'] ?? null;

            $tubes[$j]['startFiberIndex'] = $target;
            $tubes[$j]['endFiberIndex'] = $target;
            $tubes[$j]['startOffsetM'] = $startOffset;
            if (is_numeric($len)) {
                $tubes[$j]['endOffsetM'] = (float)$startOffset + (float)$len;
            }
        }

        $this->config['tubes'] = $tubes;
    }

    private function normalizeConfigUnitsToM(): void
    {
        $fibers = $this->config['fibers'] ?? [];
        if (is_array($fibers)) {
            foreach ($fibers as $i => $fiber) {
                if (!is_array($fiber)) {
                    continue;
                }

                if (!array_key_exists('lengthM', $fiber)) {
                    $lengthM = $this->extractLengthM($fiber, 'lengthM', 'lengthMm');
                    if ($lengthM !== null) {
                        $fibers[$i]['lengthM'] = $lengthM;
                    }
                }
                if (!array_key_exists('toleranceM', $fiber)) {
                    $toleranceM = $this->extractLengthM($fiber, 'toleranceM', 'toleranceMm');
                    if ($toleranceM !== null) {
                        $fibers[$i]['toleranceM'] = $toleranceM;
                    }
                }
                unset($fibers[$i]['lengthMm'], $fibers[$i]['toleranceMm']);
            }
            $this->config['fibers'] = $fibers;
        }

        $tubes = $this->config['tubes'] ?? [];
        if (is_array($tubes)) {
            foreach ($tubes as $j => $tube) {
                if (!is_array($tube)) {
                    continue;
                }

                foreach ([
                    ['new' => 'startOffsetM', 'old' => 'startOffsetMm'],
                    ['new' => 'endOffsetM', 'old' => 'endOffsetMm'],
                    ['new' => 'lengthM', 'old' => 'lengthMm'],
                    ['new' => 'toleranceM', 'old' => 'toleranceMm'],
                ] as $mapping) {
                    if (!array_key_exists($mapping['new'], $tube)) {
                        $valueM = $this->extractLengthM($tube, $mapping['new'], $mapping['old']);
                        if ($valueM !== null) {
                            $tubes[$j][$mapping['new']] = $valueM;
                        }
                    }
                    unset($tubes[$j][$mapping['old']]);
                }
            }
            $this->config['tubes'] = $tubes;
        }
    }

    private function extractLengthM(array $row, string $primaryKey, string $legacyKey): ?float
    {
        $value = $row[$primaryKey] ?? null;
        if (is_numeric($value)) {
            return (float)$value;
        }

        $legacyValue = $row[$legacyKey] ?? null;
        if (is_numeric($legacyValue)) {
            return (float)$legacyValue / 1000;
        }

        return null;
    }

    private function markToleranceAutoByPath(string $name, mixed $value): void
    {
        $isAuto = ($value === null || $value === '');

        if (preg_match('/^config\.fibers\.(\d+)\.toleranceM$/', $name, $m)) {
            $idx = (int)$m[1];
            if (isset($this->config['fibers'][$idx])) {
                $this->config['fibers'][$idx]['toleranceAuto'] = $isAuto;
            }
            return;
        }

        if (preg_match('/^config\.tubes\.(\d+)\.toleranceM$/', $name, $m)) {
            $idx = (int)$m[1];
            if (isset($this->config['tubes'][$idx])) {
                $this->config['tubes'][$idx]['toleranceAuto'] = $isAuto;
            }
        }
    }

    /**
     * SVGに必要な分だけチェック（DSL実装前の暫定）
     * @return array<int, array{path:string,message:string,level?:string}>
     */
    private function validateConfigForSvg(array $config): array
    {
        $errors = [];

        $mfdCount = (int)($config['mfdCount'] ?? 1);
        if ($mfdCount < 1 || $mfdCount > 10) {
            $errors[] = ['path' => 'mfdCount', 'message' => 'mfdCountは1〜10です'];
        }

        $fiberCount = $mfdCount + 1;
        $fibers = $config['fibers'] ?? [];
        if (!is_array($fibers) || count($fibers) !== $fiberCount) {
            $errors[] = ['path' => 'fibers', 'message' => 'fibers配列の個数が不正です'];
        }

        // チューブ開始位置（MFD基準±m）
        $errors = array_merge($errors, $this->validateTubesStartPosition($config));

        return $errors;
    }

    /**
     * チューブ開始位置のエラー判定（path設計を含む）
     * @return array<int, array{path:string,message:string}>
     */
    private function validateTubesStartPosition(array $config): array
    {
        $errors = [];

        $mfdCount = (int)($config['mfdCount'] ?? 1);
        $mfdCount = max(1, min(10, $mfdCount));
        $fiberCount = $mfdCount + 1;

        // fiber長さ（未入力に備えた暫定値）
        $fallbackPerSeg = 0.1;
        $fibers = $config['fibers'] ?? [];
        $segLens = [];

        for ($i = 0; $i < $fiberCount; $i++) {
            $len = $this->extractLengthM($fibers[$i] ?? [], 'lengthM', 'lengthMm');
            $segLens[$i] = (is_numeric($len) && (float)$len > 0) ? (float)$len : $fallbackPerSeg;
        }

        $totalLen = array_sum($segLens);

        // MFD[k]の位置（m）= fiber[k]の終端
        $mfdPos = [];
        $cum = 0.0;
        for ($i = 0; $i < $fiberCount; $i++) {
            $cum += $segLens[$i];
            if ($i < $mfdCount) $mfdPos[$i] = $cum;
        }

        $tubes = $config['tubes'] ?? [];
        if (!is_array($tubes)) return $errors;

        foreach ($tubes as $j => $tube) {
            if (!is_array($tube)) {
                continue;
            }
            // 1) anchor.index（MFD番号）
            $aIdx = $tube['anchor']['index'] ?? null;
            if (!is_numeric($aIdx)) {
                $errors[] = ['path' => "tubes.$j.anchor.index", 'message' => 'anchor.index（MFD番号）が数値ではありません'];
                continue;
            }
            $aIdx = (int)$aIdx;
            if ($aIdx < 0 || $aIdx > $mfdCount - 1) {
                $errors[] = ['path' => "tubes.$j.anchor.index", 'message' => "anchor.indexは0〜".($mfdCount-1)."です"];
                continue;
            }

            // 2) startOffsetM（±m）
            $offset = $this->extractLengthM($tube, 'startOffsetM', 'startOffsetMm');
            if (!is_numeric($offset)) {
                $errors[] = ['path' => "tubes.$j.startOffsetM", 'message' => 'startOffsetM（±m）が数値ではありません'];
                continue;
            }
            $offset = (float)$offset;

            // 3) lengthM（チューブ長）
            $lenM = $this->extractLengthM($tube, 'lengthM', 'lengthMm');
            if (!is_numeric($lenM)) {
                $errors[] = ['path' => "tubes.$j.lengthM", 'message' => 'チューブ長さが数値ではありません'];
                continue;
            }
            $lenM = (float)$lenM;
            if ($lenM <= 0) {
                $errors[] = ['path' => "tubes.$j.lengthM", 'message' => 'チューブ長さは0より大きくしてください'];
                continue;
            }

            // 開始・終了（m）
            $anchorM = $mfdPos[$aIdx] ?? 0.0;
            $startM = $anchorM + $offset;
            $endM = $startM + $lenM;

            // 範囲チェック（0..totalLen）
            if ($startM < 0 || $startM > $totalLen) {
                $errors[] = ['path' => "tubes.$j.startOffsetM", 'message' => "開始位置が範囲外です（0〜{$totalLen}m）"];
            }
            if ($endM < 0 || $endM > $totalLen) {
                $errors[] = ['path' => "tubes.$j.lengthM", 'message' => "終了位置が範囲外です（0〜{$totalLen}m）"];
            }
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $raw
     * @return array<int, string>
     */
    private function normalizeSummaryFields(array $raw): array
    {
        $allowed = array_keys($this->summaryFieldOptions ?: self::SUMMARY_FIELD_LABELS);
        $selected = [];
        foreach ($raw as $field) {
            $field = (string)$field;
            if ($field === 'account_display_name') {
                $field = 'account_internal_name';
            }
            if (!in_array($field, $allowed, true)) {
                continue;
            }
            if (!in_array($field, $selected, true)) {
                $selected[] = $field;
            }
        }

        return empty($selected) ? self::SUMMARY_DEFAULT_FIELDS : $selected;
    }

    public function newSession(): void
    {
        // 見積編集モードでは sessions を増やさない
        if ($this->quoteEditId) {
            return;
        }

        $cookieName = 'config_session_id';
        $templateVersionId = $this->templateVersionId;
        if (!$templateVersionId) {
            $templateVersionId = $this->ensureTemplateVersionId();
        }
        $config = $this->loadTemplateConfig($templateVersionId) ?? $this->defaultConfig();

        $session = ConfiguratorSession::create([
            'account_id' => $this->resolveAccountId(),
            'template_version_id' => $templateVersionId,
            'status' => 'DRAFT',
            'config' => $config,
            'derived' => [],
            'validation_errors' => [],
        ]);

        $this->sessionId = $session->id;
        $this->templateVersionId = (int)$session->template_version_id;
        $this->templateDsl = $this->loadTemplateDsl($this->templateVersionId) ?? [];
        $this->config = $session->config;
        $this->derived = [];
        $this->errors = [];

        Cookie::queue(
            Cookie::make($cookieName, (string)$session->id, 60 * 24 * 30)
                ->withHttpOnly(true)
                ->withSameSite('Lax')
        );
    }

    public function render()
    {
        return view('configurator')->layout('work.layout');
    }
}
