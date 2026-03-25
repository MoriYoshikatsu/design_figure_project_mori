<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_change_request_requirements')) {
            Schema::create('account_change_request_requirements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->string('entity_type', 100);
                $table->boolean('is_required')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['account_id', 'entity_type'], 'acct_change_req_unique');
                $table->index('entity_type', 'acct_change_req_entity_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_change_request_requirements');
    }
};
