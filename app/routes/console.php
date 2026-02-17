<?php

use App\Services\WorkPermissionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
