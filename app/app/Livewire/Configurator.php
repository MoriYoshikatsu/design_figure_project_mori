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
    public ?array $initialConfig = null;
    public ?int $initialTemplateVersionId = null;
    public ?string $initialMemo = null;
    public ?string $memo = null;
    public bool $isSaving = false;      // 保存中フラグ
    public ?string $saveError = null;   // 保存失敗メッセージ（なければnull）
    public bool $dirty = false;       // 未保存フラグ
    public string $saveStatus = '';   // 表示用（保存した時刻など）
    private float $lastSavedAt = 0.0;       // 最終保存時刻（秒）
    private float $saveIntervalSec = 1.0;   // 保存間隔（秒）

    public function mount(?int $quoteEditId = null, ?array $initialConfig = null, ?int $initialTemplateVersionId = null, ?string $initialMemo = null): void
    {
        if ($quoteEditId) {
            $this->quoteEditId = $quoteEditId;
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
                ['skuCode' => 'FIBER_SMF28', 'lengthMm' => 500, 'toleranceMm' => 5, 'toleranceAuto' => true],
                ['skuCode' => 'FIBER_SMF28', 'lengthMm' => 300, 'toleranceMm' => 3, 'toleranceAuto' => true],
                ['skuCode' => 'FIBER_SMF28', 'lengthMm' => 500, 'toleranceMm' => 5, 'toleranceAuto' => true],
            ],
            'tubeCount' => 1,
            'tubes' => [
                [
                    'skuCode' => 'TUBE_0.9_LOOSE',
                    'anchor' => ['type' => 'MFD', 'index' => 0],
                    'startFiberIndex' => 0,
                    'endFiberIndex' => 0,
                    'startOffsetMm' => 0,
                    'endOffsetMm' => 200,
                    'toleranceMm' => null,
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
                '%s (%s) v%s / DSL %s',
                (string)$r->template_name,
                (string)$r->template_code,
                (string)$r->version,
                (string)$r->dsl_version
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
            $this->autoSaveIfDue();
            return;
        }

        if (!str_starts_with($name, 'config.')) return;

        if (str_contains($name, '.toleranceMm')) {
            $this->markToleranceAutoByPath($name, $value);
        }

        $resizeArrays = in_array($name, ['config.mfdCount', 'config.tubeCount'], true);
        $this->recompute($resizeArrays);

        // ついでに「一定間隔で自動保存」もここで（次章）
        $this->autoSaveIfDue();
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
        $nameSource = (string)($baseSnapshot['account_display_name_source'] ?? 'internal_name');
        if (!in_array($nameSource, ['internal_name', 'user_name'], true)) {
            $nameSource = 'internal_name';
        }
        $summaryFields = $baseSnapshot['summary_card_fields'] ?? [];
        if (!is_array($summaryFields)) {
            $summaryFields = [];
        }
        $snapshot['account_display_name_source'] = $nameSource;
        $snapshot['summary_card_fields'] = $summaryFields;
        $baseSnapshot['account_display_name_source'] = $nameSource;
        $baseSnapshot['summary_card_fields'] = $summaryFields;
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
        // 1) derived（導出）
        $mfdCount = (int)($this->config['mfdCount'] ?? 1);
        $mfdCount = max(1, min(10, $mfdCount));      // 1..10 に丸める
        $this->config['mfdCount'] = $mfdCount;

        $fiberCount = $mfdCount + 1;

        // 2) counts変更時のみ arrays（配列）調整
        if ($resizeArrays) {
            // sleeves（MFD点ごと）
            $this->ensureArraySize('sleeves', $mfdCount, ['skuCode'=>null]);

            $this->ensureArraySize('fibers', $fiberCount, ['skuCode'=>null,'lengthMm'=>null,'toleranceMm'=>null,'toleranceAuto'=>true]);

            $tubeCount = (int)($this->config['tubeCount'] ?? 0);
            $tubeCount = max(0, min($tubeCount, $fiberCount));   // 0..fiberCount
            $this->config['tubeCount'] = $tubeCount;

            $this->ensureArraySize('tubes', $tubeCount, [
                'skuCode'=>null,
                'anchor'=>['type'=>'MFD','index'=>0],
                'targetFiberIndex'=>0,
                'startFiberIndex'=>0,
                'endFiberIndex'=>0,
                'startOffsetMm'=>0,
                'endOffsetMm'=>null,
                'lengthMm'=>null,
                'toleranceMm'=>null,
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

        // 2.5.1) 旧フィールド互換（targetFiberIndex/lengthMm → start/end）
        $this->migrateTubeStartEndFields();

        // 2.6) totalLengthMm を計算（未入力は暫定100mmで計算）
        //      長すぎる区間は「表示用に上限を設ける」 + 挿絵で示す
        $fallbackPerSeg = 100.0;
        $segmentCapMm = 1200.0; // 要件に合わせて調整
        $displayLens = [];
        $segmentIllustrations = [];

        foreach (($this->config['fibers'] ?? []) as $i => $f) {
            $len = $f['lengthMm'] ?? null;
            $actual = (is_numeric($len) && (float)$len > 0) ? (float)$len : $fallbackPerSeg;
            $display = min($actual, $segmentCapMm);
            $displayLens[$i] = $display;

            if ($actual > $segmentCapMm) {
                $segmentIllustrations[$i] = $this->makeSegmentIllustrationDataUrl($actual, $segmentCapMm);
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
            'segmentLengthCapMm' => $segmentCapMm,
            'totalLengthMm' => array_sum($displayLens),
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
        $this->derived['pricing'] = $pricing->price($this->resolveAccountId(), $this->derived['bom'] ?? []);

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

    private function autoTolerance(?float $lengthMm, float $rate = 0.01, int $minDefault = 1, int $min = 0, int $max = 20): ?int
    {
        if ($lengthMm === null || $lengthMm <= 0) return null;

        $v = (int)ceil($lengthMm * $rate);
        if ($v < $minDefault) $v = $minDefault;
        if ($v < $min) $v = $min;
        if ($v > $max) $v = $max;
        return $v;
    }

    private function makeSegmentIllustrationDataUrl(float $actualMm, float $capMm): string
    {
        $label = 'LONG > ' . (int)round($capMm) . 'mm';
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
            $tol = $f['toleranceMm'] ?? null;
            $auto = $f['toleranceAuto'] ?? true;

            // 手入力で固定されていないものだけ自動更新
            if ($auto === true) {
                $len = $f['lengthMm'] ?? null;
                $len = is_numeric($len) ? (float)$len : null;

                $computed = $this->autoTolerance($len);
                if ($computed !== null) {
                    $fibers[$i]['toleranceMm'] = $computed;
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
            $tol = $t['toleranceMm'] ?? null;
            $auto = $t['toleranceAuto'] ?? true;

            if ($auto === true) {
                $len = $t['lengthMm'] ?? null;
                $len = is_numeric($len) ? (float)$len : null;

                $computed = $this->autoTolerance($len);
                if ($computed !== null) {
                    $tubes[$j]['toleranceMm'] = $computed;
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

            $hasStart = array_key_exists('startFiberIndex', $t) || array_key_exists('endFiberIndex', $t) || array_key_exists('endOffsetMm', $t);
            if ($hasStart) continue;

            $target = $t['targetFiberIndex'] ?? 0;
            $startOffset = $t['startOffsetMm'] ?? 0;
            $len = $t['lengthMm'] ?? null;

            $tubes[$j]['startFiberIndex'] = $target;
            $tubes[$j]['endFiberIndex'] = $target;
            $tubes[$j]['startOffsetMm'] = $startOffset;
            if (is_numeric($len)) {
                $tubes[$j]['endOffsetMm'] = (float)$startOffset + (float)$len;
            }
        }

        $this->config['tubes'] = $tubes;
    }

    private function markToleranceAutoByPath(string $name, mixed $value): void
    {
        $isAuto = ($value === null || $value === '');

        if (preg_match('/^config\.fibers\.(\d+)\.toleranceMm$/', $name, $m)) {
            $idx = (int)$m[1];
            if (isset($this->config['fibers'][$idx])) {
                $this->config['fibers'][$idx]['toleranceAuto'] = $isAuto;
            }
            return;
        }

        if (preg_match('/^config\.tubes\.(\d+)\.toleranceMm$/', $name, $m)) {
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

        // チューブ開始位置（MFD基準±mm）
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
        $fallbackPerSeg = 100.0;
        $fibers = $config['fibers'] ?? [];
        $segLens = [];

        for ($i = 0; $i < $fiberCount; $i++) {
            $len = $fibers[$i]['lengthMm'] ?? null;
            $segLens[$i] = (is_numeric($len) && (float)$len > 0) ? (float)$len : $fallbackPerSeg;
        }

        $totalLen = array_sum($segLens);

        // MFD[k]の位置（mm）= fiber[k]の終端
        $mfdPos = [];
        $cum = 0.0;
        for ($i = 0; $i < $fiberCount; $i++) {
            $cum += $segLens[$i];
            if ($i < $mfdCount) $mfdPos[$i] = $cum;
        }

        $tubes = $config['tubes'] ?? [];
        if (!is_array($tubes)) return $errors;

        foreach ($tubes as $j => $tube) {
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

            // 2) startOffsetMm（±mm）
            $offset = $tube['startOffsetMm'] ?? null;
            if (!is_numeric($offset)) {
                $errors[] = ['path' => "tubes.$j.startOffsetMm", 'message' => 'startOffsetMm（±mm）が数値ではありません'];
                continue;
            }
            $offset = (float)$offset;

            // 3) lengthMm（チューブ長）
            $lenMm = $tube['lengthMm'] ?? null;
            if (!is_numeric($lenMm)) {
                $errors[] = ['path' => "tubes.$j.lengthMm", 'message' => 'チューブ長さが数値ではありません'];
                continue;
            }
            $lenMm = (float)$lenMm;
            if ($lenMm <= 0) {
                $errors[] = ['path' => "tubes.$j.lengthMm", 'message' => 'チューブ長さは0より大きくしてください'];
                continue;
            }

            // 開始・終了（mm）
            $anchorMm = $mfdPos[$aIdx] ?? 0.0;
            $startMm = $anchorMm + $offset;
            $endMm = $startMm + $lenMm;

            // 範囲チェック（0..totalLen）
            if ($startMm < 0 || $startMm > $totalLen) {
                $errors[] = ['path' => "tubes.$j.startOffsetMm", 'message' => "開始位置が範囲外です（0〜{$totalLen}mm）"];
            }
            if ($endMm < 0 || $endMm > $totalLen) {
                $errors[] = ['path' => "tubes.$j.lengthMm", 'message' => "終了位置が範囲外です（0〜{$totalLen}mm）"];
            }
        }

        return $errors;
    }

    public function newSession(): void
    {
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
