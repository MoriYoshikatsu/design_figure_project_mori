<?php

use App\Services\QuoteCalcRunRecorder;
use App\Services\WorkPermissionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('permissions:migrate-to-work', function () {
    /** @var WorkPermissionService $service */
    $service = app(WorkPermissionService::class);
    $catalogCount = $service->syncCatalog();
    $grantsCount = $service->migrateLegacySalesPermissionsToWork();

    $this->info('work_permission_catalog synced: ' . $catalogCount);
    $this->info('work_permission_grants migrated/updated: ' . $grantsCount);
})->purpose('Sync work permission catalog and migrate legacy rows when legacy table exists');

Artisan::command('work:backfill-quote-calc-runs {--chunk=200}', function () {
    $chunk = max(1, (int)$this->option('chunk'));
    $processed = 0;
    $inserted = 0;
    $skipped = 0;

    /** @var QuoteCalcRunRecorder $recorder */
    $recorder = app(QuoteCalcRunRecorder::class);

    DB::table('quotes')
        ->whereNull('deleted_at')
        ->orderBy('id')
        ->chunkById($chunk, function ($quotes) use (&$processed, &$inserted, &$skipped, $recorder) {
            foreach ($quotes as $quote) {
                $processed++;
                $exists = DB::table('quote_calc_runs')
                    ->where('quote_id', (int)$quote->id)
                    ->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $snapshot = is_array($quote->snapshot)
                    ? $quote->snapshot
                    : json_decode((string)($quote->snapshot ?? ''), true);
                if (!is_array($snapshot)) {
                    $snapshot = [];
                }

                if (!is_array($snapshot['totals'] ?? null)) {
                    $snapshot['totals'] = [
                        'subtotal' => (float)($quote->subtotal ?? 0),
                        'tax' => (float)($quote->tax_total ?? 0),
                        'total' => (float)($quote->total ?? 0),
                    ];
                }
                if (!is_array($snapshot['pricing_input'] ?? null)) {
                    $snapshot['pricing_input'] = [
                        'order_qty' => (int)($quote->order_qty ?? 1),
                        'fixed_cost' => $quote->fixed_cost ?? null,
                        'management_factor' => $quote->management_factor ?? null,
                        'qty_discount_factor' => $quote->qty_discount_factor ?? null,
                        'customer_factor' => $quote->customer_factor ?? null,
                        'freight_amount' => $quote->freight_amount ?? null,
                        'manual_discount_amount' => $quote->manual_discount_amount ?? null,
                        'trade_scope' => $quote->trade_scope ?? null,
                        'tax_rate' => $quote->tax_rate ?? null,
                        'pricing_policy_id' => $quote->pricing_policy_id ?? null,
                    ];
                }

                $recorder->recordLegacyBaseline((int)$quote->id, $snapshot, null);
                $inserted++;
            }
        });

    $this->info('processed: ' . $processed);
    $this->info('inserted: ' . $inserted);
    $this->info('skipped(existing): ' . $skipped);
})->purpose('Backfill legacy quotes into append-only quote calc runs (idempotent)');
