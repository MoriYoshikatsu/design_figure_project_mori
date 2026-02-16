<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Model（モデル：DB操作クラス）
use App\Models\User;
use App\Models\ConfiguratorSession;
use App\Models\ConfiguratorSession as SessionModel;

final class RealDataSeeder extends Seeder
{
    public function run(): void
    {
        // 乱数（random：ランダム）を毎回同じにしたいなら seed（シード：種）を固定
        // mt_srand(1234);

        DB::transaction(function () {
            // =========
            // 0) users（Laravel標準）
            // =========
            $fixed = $this->seedFixedUsers();

            // =========
            // 1) accounts / account_user
            // =========
            $this->seedThreeRoleAccounts($fixed);

            // =========
            // 2) skus
            // =========
            $skuRows = $this->seedSkus();         // だいたい10〜15件
            $skuIdByCode = DB::table('skus')->pluck('id', 'sku_code')->all();

            // =========
            // 3) price_books / price_book_items
            // =========
            $priceBookIds = $this->seedPriceBooks(1);
            $this->seedPriceBookItems($priceBookIds, $skuIdByCode);

            // =========
            // 4) product_templates / product_template_versions
            // =========
            $templateVersionIds = $this->seedTemplatesAndVersions(1);

            // =========
            // 5) configurator_sessions（config/derived/validation_errors）
            // =========
            // $sessionIds = $this->seedConfiguratorSessions(1, $accountIds, $templateVersionIds);
        });
    }

    // -------------------------
    // users
    // -------------------------
    private function seedFixedUsers(): array
    {
        $users = [
            'admin@gmail.com' => ['name' => 'Admin', 'password' => '1234567890'],
            'sales@gmail.com' => ['name' => 'Sales', 'password' => '1234567890'],
            'customer@gmail.com' => ['name' => 'Customer', 'password' => '1234567890'],
        ];

        $ids = [];
        foreach ($users as $email => $u) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                ]
            );
            $ids[$email] = $user->id;
        }

        return $ids;
    }

    // -------------------------
    // accounts
    // -------------------------

    /**
     * admin / sales / customer の3権限それぞれ専用のアカウントを作成し、
     * 対応ユーザーを1名ずつ紐づける。
     *
     * @param array<string,int> $fixedUserIds
     * @return array<string,int> role => account_id
     */
    private function seedThreeRoleAccounts(array $fixedUserIds): array
    {
        $defs = [
            'admin' => [
                'account_name' => '社長',
                'account_type' => 'B2B',
                'user_email' => 'admin@gmail.com',
            ],
            'sales' => [
                'account_name' => '森',
                'account_type' => 'B2B',
                'user_email' => 'sales@gmail.com',
            ],
            'customer' => [
                'account_name' => '顧客',
                'account_type' => 'B2C',
                'user_email' => 'customer@gmail.com',
            ],
        ];

        $accountIds = [];

        foreach ($defs as $role => $def) {
            $accountId = (int)DB::table('accounts')
                ->where('internal_name', $def['account_name'])
                ->value('id');

            if ($accountId <= 0) {
                $accountId = (int)DB::table('accounts')->insertGetId([
                    'account_type' => $def['account_type'],
                    'internal_name' => $def['account_name'],
                    'sales_route_policy_mode' => 'strict_allowlist',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $accountIds[$role] = $accountId;

            $userId = $fixedUserIds[$def['user_email']] ?? null;
            if (!$userId) {
                continue;
            }

            DB::table('account_user')->updateOrInsert(
                ['account_id' => $accountId, 'user_id' => (int)$userId],
                $this->pivotRow($accountId, (int)$userId, $role)
            );
        }

        return $accountIds;
    }

    // -------------------------
    // account_user（pivot：中間テーブル）
    // -------------------------
    private function pivotRow(int $accountId, int $userId, string $role): array
    {
        return [
            'account_id' => $accountId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function seedFixedAccountUsers(int $accountId, array $fixedUserIds): void
    {
        $map = [
            'admin@gmail.com' => 'admin',
            'sales@gmail.com' => 'sales',
            'customer@gmail.com' => 'customer',
        ];

        foreach ($map as $email => $role) {
            $userId = $fixedUserIds[$email] ?? null;
            if (!$userId) continue;
            DB::table('account_user')->updateOrInsert(
                ['account_id' => $accountId, 'user_id' => $userId],
                $this->pivotRow($accountId, $userId, $role)
            );
        }
    }

    // -------------------------
    // skus
    // -------------------------
    private function seedSkus(): array
    {
        // だいたい 2件×5カテゴリ + α
        $rows = [
            // PROC（工程）
            $this->skuRow('PROC_MFD', 'MFD変換', 'PROC', ['kind' => 'mfd']),
            $this->skuRow('PROC_FBG',  'FBGセンサ', 'PROC', ['kind' => 'fbg']),
            $this->skuRow('PROC_TEC', 'TEC加工', 'PROC', ['kind' => 'tec']),

            // SLEEVE（補強）
            $this->skuRow('SLEEVE_RECOTE', 'リコート', 'SLEEVE', ['material' => 'polymer']),
            $this->skuRow('SLEEVE_SPRICESLEEVE', '補強スリーブ', 'SLEEVE', ['material' => 'metal']),
            $this->skuRow('SLEEVE_SUS_PIPE', 'SUSパイプ', 'SLEEVE', ['material' => 'sus']),

            // FIBER（ファイバ）
            $this->skuRow('FIBER_SMF28E+', 'SMF28e+', 'FIBER', ['mfd' => '9/125', 'minLen' => 50, 'maxLen' => 10000]),
            $this->skuRow('FIBER_UHNA1', 'UHNA1', 'FIBER', ['mfd' => '10/125', 'minLen' => 50, 'maxLen' => 10000]),
            $this->skuRow('FIBER_PMF', 'PMF', 'FIBER', ['mfd' => '10/125', 'minLen' => 50, 'maxLen' => 10000]),

            // TUBE（チューブ）
            $this->skuRow('TUBE_0.9_LOOSE', 'Φ0.9ルースチューブ', 'TUBE', ['minLen' => 30, 'maxLen' => 10000]),

            // CONNECTOR（コネクタ：端子）
            $this->skuRow('CONN_FERRULE_PC', 'フェルール/PCコネクタ', 'CONNECTOR', ['polish' => 'PC']),
            $this->skuRow('CONN_FERRULE_APC', 'フェルール/APCコネクタ', 'CONNECTOR', ['polish' => 'APC']),
            $this->skuRow('CONN_FERRULE_ARCOAT', 'フェルール/ARコート', 'CONNECTOR', ['polish' => 'ARcoat']),
            $this->skuRow('CONN_FC_PC', 'FC/PCコネクタ', 'CONNECTOR', ['polish' => 'PC']),
            $this->skuRow('CONN_FC_APC', 'FC/APCコネクタ', 'CONNECTOR', ['polish' => 'APC']),
            $this->skuRow('CONN_FC_ARCOAT', 'FC/ARコート', 'CONNECTOR', ['polish' => 'ARcoat']),
            $this->skuRow('CONN_SC_PC', 'SC/PCコネクタ', 'CONNECTOR', ['polish' => 'PC']),
            $this->skuRow('CONN_SC_APC', 'SC/APCコネクタ', 'CONNECTOR', ['polish' => 'APC']),
            $this->skuRow('CONN_SC_ARCOAT', 'SC/ARコート', 'CONNECTOR', ['polish' => 'ARcoat']),
            $this->skuRow('CONN_LC_PC', 'LC/PCコネクタ', 'CONNECTOR', ['polish' => 'PC']),
            $this->skuRow('CONN_LC_APC', 'LC/APCコネクタ', 'CONNECTOR', ['polish' => 'APC']),
            $this->skuRow('CONN_LC_ARCOAT', 'LC/ARコート', 'CONNECTOR', ['polish' => 'ARcoat']),
        ];

        foreach ($rows as $r) {
            DB::table('skus')->updateOrInsert(
                ['sku_code' => $r['sku_code']],
                $r
            );
        }

        return $rows;
    }

    private function skuRow(string $code, string $name, string $category, array $attrs): array
    {
        return [
            'sku_code' => $code,
            'name' => $name,
            'category' => $category,
            'active' => true,
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // -------------------------
    // price_books
    // -------------------------
    private function seedPriceBooks(int $n): array
    {
        $existing = DB::table('price_books')->count();
        $need = max(0, $n - $existing);

        if ($need > 0) {
            $rows = [];
            for ($i = 1; $i <= $need; $i++) {
                $idx = $existing + $i;
                $rows[] = [
                    'name' => 'STANDARD',
                    'version' => $idx,
                    'currency' => 'JPY',
                    'valid_from' => now()->toDateString(),
                    'valid_to' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            // unique(name,version)なので insertでOK
            DB::table('price_books')->insert($rows);
        }

        return DB::table('price_books')->orderBy('id')->limit($n)->pluck('id')->all();
    }

    // -------------------------
    // price_book_items
    // -------------------------
    private function seedPriceBookItems(array $priceBookIds, array $skuIdByCode): void
    {
        $skuIds = array_values($skuIdByCode);
        if (count($skuIds) === 0) return;

        $skuRows = DB::table('skus')
            ->whereIn('id', $skuIds)
            ->orderBy('id')
            ->get(['id', 'sku_code', 'category']);
        if ($skuRows->isEmpty()) return;

        // 再シード時に重複しないよう、対象価格表の明細を入れ直す
        DB::table('price_book_items')->whereIn('price_book_id', $priceBookIds)->delete();

        $fixedBaseByCategory = [
            'PROC' => 8500.0,
            'SLEEVE' => 1300.0,
            'CONNECTOR' => 2800.0,
        ];

        $perMmBaseByCategory = [
            'FIBER' => 1.20,
            'TUBE' => 0.75,
        ];

        $rows = [];
        foreach ($priceBookIds as $pbId) {
            foreach ($skuRows as $idx => $sku) {
                $category = strtoupper((string)$sku->category);
                $codeHash = abs(crc32((string)$sku->sku_code));
                $priceBump = (float)(($codeHash % 7) * 100); // 固定単価の軽いバリエーション
                $perMmBump = (float)(($codeHash % 5) * 0.03); // mm単価の軽いバリエーション

                if (in_array($category, ['FIBER', 'TUBE'], true)) {
                    $rows[] = [
                        'price_book_id' => (int)$pbId,
                        'sku_id' => (int)$sku->id,
                        'pricing_model' => 'PER_MM',
                        'unit_price' => null,
                        'price_per_mm' => ($perMmBaseByCategory[$category] ?? 1.0) + $perMmBump,
                        'formula' => null,
                        'min_qty' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                $rows[] = [
                    'price_book_id' => (int)$pbId,
                    'sku_id' => (int)$sku->id,
                    'pricing_model' => 'FIXED',
                    'unit_price' => ($fixedBaseByCategory[$category] ?? 1000.0) + $priceBump + ($idx * 10),
                    'price_per_mm' => null,
                    'formula' => null,
                    'min_qty' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($rows)) {
            DB::table('price_book_items')->insert($rows);
        }
    }

    // -------------------------
    // product_templates / product_template_versions
    // -------------------------
    private function seedTemplatesAndVersions(int $n): array
    {
        $templateIds = [];

        for ($i = 1; $i <= $n; $i++) {
            $code = sprintf('TPL_%02d', $i);

            DB::table('product_templates')->updateOrInsert(
                ['template_code' => $code],
                [
                    'name' => "Demo Template {$i}",
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $templateId = DB::table('product_templates')->where('template_code', $code)->value('id');
            $templateIds[] = $templateId;

            // version=1固定で10件（unique(template_id,version)）
            DB::table('product_template_versions')->updateOrInsert(
                ['template_id' => $templateId, 'version' => 1],
                [
                    'dsl_version' => '0.2',
                    'dsl_json' => json_encode([
                        'template_code' => $code,
                        'mfdCount' => ['min' => 1, 'max' => 10],
                        'note' => 'demo dsl',
                        'default_config' => [
                            'mfdCount' => ($i % 5) + 1,
                            'tubeCount' => $i % 3,
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // versionのid配列を返す
        return DB::table('product_template_versions')
            ->orderBy('id')
            ->limit($n)
            ->pluck('id')
            ->all();
    }

    // -------------------------
    // configurator_sessions
    // -------------------------
    private function seedConfiguratorSessions(int $n, array $accountIds, array $templateVersionIds): array
    {
        $ids = [];

        for ($i = 0; $i < $n; $i++) {
            $accountId = $accountIds[$i % count($accountIds)];
            $tplVerId  = $templateVersionIds[$i % count($templateVersionIds)];

            $mfdCount = ($i % 5) + 1; // 1..5
            $fiberCount = $mfdCount + 1;

            $fibers = [];
            for ($k = 0; $k < $fiberCount; $k++) {
                $len = 200 + ($k * 50) + ($i * 10);
                $fibers[] = [
                    'skuCode' => ($k % 2 === 0) ? 'FIBER_A' : 'FIBER_B',
                    'lengthMm' => $len,
                    'toleranceMm' => max(1, (int)round($len * 0.01)),
                ];
            }

            $tubes = [];
            $tubeCount = $i % 3; // 0..2
            for ($t = 0; $t < $tubeCount; $t++) {
                $tubes[] = [
                    'skuCode' => ($t % 2 === 0) ? 'TUBE_X' : 'TUBE_Y',
                    'anchor' => ['type' => 'MFD', 'index' => min($mfdCount - 1, $t)],
                    'startOffsetMm' => ($t === 0) ? -10 : 30,
                    'lengthMm' => 150 + $t * 20,
                    'toleranceMm' => null,
                ];
            }

            $config = [
                'mfdCount' => $mfdCount,
                'sleeveSkuCode' => ['SLEEVE_RECOTE', 'SLEEVE_REINFORCE', 'SLEEVE_SUS_PIPE'][$i % 3],
                'fibers' => $fibers,
                'tubeCount' => $tubeCount,
                'tubes' => $tubes,
                'connectors' => [
                    'leftSkuCode' => ($i % 2 === 0) ? 'CONN_SC_UPC' : null,
                    'rightSkuCode' => ($i % 3 === 0) ? 'CONN_SC_APC' : null,
                ],
            ];

            $derived = [
                'fiberCount' => $fiberCount,
            ];

            $validationErrors = []; // 初期は空（必要ならダミーも可）

            $id = DB::table('configurator_sessions')->insertGetId([
                'account_id' => $accountId,
                'template_version_id' => $tplVerId,
                'status' => 'DRAFT',
                'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'derived' => json_encode($derived, JSON_UNESCAPED_UNICODE),
                'validation_errors' => json_encode($validationErrors, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids[] = $id;
        }

        return $ids;
    }
}
