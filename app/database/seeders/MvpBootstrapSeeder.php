<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MvpBootstrapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \DB::table('accounts')->updateOrInsert(
            ['id' => 1],
            ['account_type' => 'B2B', 'name' => 'MVP', 'created_at' => now(), 'updated_at' => now()]
        );

        \DB::table('product_templates')->updateOrInsert(
            ['id' => 1],
            ['template_code' => 'MFD_CONVERSION_FIBER', 'name' => 'MFD MVP', 'active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        \DB::table('product_template_versions')->updateOrInsert(
            ['id' => 1],
            [
                'template_id' => 1,
                'version' => 1,
                'dsl_version' => '0.2',
                'dsl_json' => json_encode([
                    'default_config' => [
                        'processType' => 'MFD',
                        'mfdCount' => 1,
                        'tubeCount' => 1,
                        'tecSide' => null,
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
