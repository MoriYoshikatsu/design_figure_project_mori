<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ConfiguratorSession extends Model
{
    protected $table = 'configurator_sessions';

    // JSON（ジェイソン：構造データ）を配列（array（配列：リスト/辞書））として扱う
    protected $casts = [
        'config' => 'array',
        'derived' => 'array',
        'validation_errors' => 'array',
    ];

    // 一括代入（mass assignment：まとめて代入）許可
    protected $fillable = [
        'account_id',
        'template_version_id',
        'status',
        'spec_sheet_number',
        'config',
        'derived',
        'validation_errors',
        'memo',
    ];
}
