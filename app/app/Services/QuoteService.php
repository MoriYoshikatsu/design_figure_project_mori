<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class QuoteService
{
    /**
     * @return int quote_id
     */
    public function createFromSession(
        int $sessionId,
        ?int $actorUserId = null,
        bool $lockSession = false,
        array $pricingInput = []
    ): int
    {
        return DB::transaction(function () use ($sessionId, $actorUserId, $lockSession, $pricingInput) {
            $session = DB::table('configurator_sessions')
                ->where('id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (!$session) {
                throw new \RuntimeException("configurator_sessions not found: {$sessionId}");
            }

            // 直後ログインユーザーでの発行時、未紐付けセッションはユーザーaccountへ引き継ぐ。
            // 既に他ユーザーに紐付いたaccountなら発行不可にする。
            if ($actorUserId) {
                $belongsToActor = DB::table('account_user')
                    ->where('account_id', (int)$session->account_id)
                    ->where('user_id', $actorUserId)
                    ->exists();

                if (!$belongsToActor) {
                    $linkedToAnyUser = DB::table('account_user')
                        ->where('account_id', (int)$session->account_id)
                        ->exists();

                    if ($linkedToAnyUser) {
                        throw new \RuntimeException('forbidden: session account does not belong to actor user');
                    }

                    $targetAccountId = $this->resolveOrCreateActorAccountId($actorUserId);
                    DB::table('configurator_sessions')
                        ->where('id', (int)$session->id)
                        ->update([
                            'account_id' => $targetAccountId,
                            'updated_at' => now(),
                        ]);
                    $session->account_id = $targetAccountId;
                }
            }

            $config = $this->decodeJson($session->config) ?? [];
            $derived = $this->decodeJson($session->derived) ?? [];
            $validationErrors = $this->decodeJson($session->validation_errors) ?? [];

            $dsl = $this->loadTemplateDsl((int)$session->template_version_id) ?? [];

            /** @var \App\Services\DslEngine $dslEngine */
            $dslEngine = app(\App\Services\DslEngine::class);
            $eval = $dslEngine->evaluate($config, $dsl);
            $derived = array_merge($derived, $eval['derived'] ?? []);
            $validationErrors = $eval['errors'] ?? $validationErrors;

            /** @var \App\Services\BomBuilder $bomBuilder */
            $bomBuilder = app(\App\Services\BomBuilder::class);
            $bom = $bomBuilder->build($config, $derived, $dsl);

            /** @var \App\Services\PricingService $pricing */
            $pricing = app(\App\Services\PricingService::class);
            $pricingResult = $pricing->price((int)$session->account_id, $bom);

            /** @var \App\Services\QuoteCalculationEngine $quoteCalculationEngine */
            $quoteCalculationEngine = app(\App\Services\QuoteCalculationEngine::class);
            $calculation = $quoteCalculationEngine->calculate(
                (int)$session->account_id,
                $bom,
                is_array($pricingResult['items'] ?? null) ? $pricingResult['items'] : [],
                array_merge(
                    is_array($pricingInput) ? $pricingInput : [],
                    ['currency' => (string)($pricingResult['currency'] ?? 'JPY')]
                )
            );
            $pricingInputResolved = is_array($calculation['pricing_input'] ?? null) ? $calculation['pricing_input'] : [];
            $pricingOutputResolved = is_array($calculation['pricing_output'] ?? null) ? $calculation['pricing_output'] : [];
            $resolvedTotals = is_array($calculation['totals'] ?? null) ? $calculation['totals'] : [];

            $insertPayload = [
                'account_id' => (int)$session->account_id,
                'session_id' => (int)$session->id,
                'status' => 'ISSUED',
                'currency' => (string)($pricingResult['currency'] ?? 'JPY'),
                'memo' => $session->memo,
                'subtotal' => (float)($resolvedTotals['subtotal'] ?? 0),
                'discount_total' => 0,
                'tax_total' => (float)($resolvedTotals['tax'] ?? 0),
                'total' => (float)($resolvedTotals['total'] ?? 0),
                'snapshot' => json_encode([
                    'template_version_id' => (int)$session->template_version_id,
                    'price_book_id' => $pricingResult['price_book_id'] ?? null,
                    'summary_card_fields' => [
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
                    ],
                    'config' => $config,
                    'derived' => $derived,
                    'validation_errors' => $validationErrors,
                    'bom' => $bom,
                    'pricing' => $pricingResult['items'] ?? [],
                    'pricing_input' => $pricingInputResolved,
                    'pricing_steps' => $calculation['pricing_steps'] ?? [],
                    'pricing_output' => $pricingOutputResolved,
                    'totals' => [
                        'subtotal' => (float)($resolvedTotals['subtotal'] ?? 0),
                        'tax' => (float)($resolvedTotals['tax'] ?? 0),
                        'total' => (float)($resolvedTotals['total'] ?? 0),
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $optionalQuoteColumns = [
                'order_qty' => (int)($pricingInputResolved['order_qty'] ?? 1),
                'fixed_cost' => $pricingInputResolved['fixed_cost'] ?? null,
                'management_factor' => $pricingInputResolved['management_factor'] ?? null,
                'qty_discount_factor' => $pricingInputResolved['qty_discount_factor'] ?? null,
                'customer_factor' => $pricingInputResolved['customer_factor'] ?? null,
                'freight_amount' => $pricingInputResolved['freight_amount'] ?? null,
                'manual_discount_amount' => $pricingInputResolved['manual_discount_amount'] ?? 0,
                'trade_scope' => $pricingInputResolved['trade_scope'] ?? 'DOMESTIC',
                'tax_rate' => $pricingInputResolved['tax_rate'] ?? null,
                'pricing_policy_id' => $pricingInputResolved['pricing_policy_id'] ?? null,
            ];
            foreach ($optionalQuoteColumns as $column => $value) {
                if ($this->hasQuoteColumn($column)) {
                    $insertPayload[$column] = $value;
                }
            }

            $quoteId = (int)DB::table('quotes')->insertGetId($insertPayload);

            $this->insertQuoteItems($quoteId, $bom, $pricingResult['items'] ?? []);

            /** @var \App\Services\QuoteCalcRunRecorder $runRecorder */
            $runRecorder = app(\App\Services\QuoteCalcRunRecorder::class);
            $runRecorder->recordFromCalculation(
                $quoteId,
                'ISSUE',
                $calculation,
                $actorUserId ? (int)$actorUserId : null,
                true,
                'quote',
                $quoteId,
                ['session_id' => (int)$session->id]
            );

            DB::table('configurator_sessions')
                ->where('id', $session->id)
                ->update([
                    'status' => $lockSession ? 'LOCKED' : 'QUOTED',
                    'updated_at' => now(),
                ]);

            if ($actorUserId) {
                DB::table('audit_logs')->insert([
                    'actor_user_id' => $actorUserId,
                    'action' => 'QUOTE_ISSUED',
                    'entity_type' => 'quote',
                    'entity_id' => $quoteId,
                    'before_json' => null,
                    'after_json' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $quoteId;
        });
    }

    private function resolveOrCreateActorAccountId(int $userId): int
    {
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
        if ($accountId > 0) {
            return $accountId;
        }

        $userName = (string)(DB::table('users')->where('id', $userId)->value('name') ?? '');
        $accountId = (int)DB::table('accounts')->insertGetId([
            'account_type' => 'B2C',
            'internal_name' => trim($userName) !== '' ? $userName : null,
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
    }

    private function insertQuoteItems(int $quoteId, array $bom, array $pricingItems): void
    {
        $pricingBySort = [];
        foreach ($pricingItems as $pi) {
            if (!is_array($pi)) continue;
            $pricingBySort[(int)($pi['sort_order'] ?? 0)] = $pi;
        }

        $skuCodes = array_values(array_unique(array_filter(array_map(
            fn ($r) => is_array($r) ? ($r['sku_code'] ?? null) : null,
            $bom
        ))));

        $skuIdByCode = [];
        if (!empty($skuCodes)) {
            $skuIdByCode = DB::table('skus')
                ->whereIn('sku_code', $skuCodes)
                ->pluck('id', 'sku_code')
                ->all();
        }

        $rows = [];
        foreach ($bom as $row) {
            if (!is_array($row)) continue;
            $skuCode = (string)($row['sku_code'] ?? '');
            if ($skuCode === '') continue;
            $skuId = $skuIdByCode[$skuCode] ?? null;
            if (!$skuId) continue;

            $sort = (int)($row['sort_order'] ?? 0);
            $pricing = $pricingBySort[$sort] ?? null;

            $qty = $this->asNumber($row['quantity'] ?? 1);
            $unitPrice = $this->asNumber($pricing['unit_price'] ?? 0);
            $lineTotal = $this->asNumber($pricing['line_total'] ?? ($unitPrice * $qty));

            $rows[] = [
                'quote_id' => $quoteId,
                'sku_id' => $skuId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'options' => json_encode($row['options'] ?? [], JSON_UNESCAPED_UNICODE),
                'source_path' => $row['source_path'] ?? null,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('quote_items')->insert($rows);
        }
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

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if ($value === null) return null;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function asNumber(mixed $v): float
    {
        return is_numeric($v) ? (float)$v : 0.0;
    }

    private function hasQuoteColumn(string $column): bool
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasTable('quotes') && Schema::hasColumn('quotes', $column);
        }

        return $cache[$column];
    }
}
