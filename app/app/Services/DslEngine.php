<?php

namespace App\Services;

final class DslEngine
{
    /**
     * @return array{derived: array, errors: array}
     */
    public function evaluate(array $config, array $dsl): array
    {
        $errors = [];

        $processType = $this->normalizeProcessType($config['processType'] ?? 'MFD');
        if ($processType === null) {
            $errors[] = ['path' => 'processType', 'message' => 'processTypeは MFD / TEC20 / TEC30 / TEC20_HP / TEC30_HP のいずれかです'];
            $processType = 'MFD';
        }
        $isTecMode = in_array($processType, ['TEC20', 'TEC30', 'TEC20_HP', 'TEC30_HP'], true);

        $mfdCount = (int)($config['mfdCount'] ?? 1);
        $fiberCount = $isTecMode ? 1 : ($mfdCount + 1);

        $this->validateRange('mfdCount', $config['mfdCount'] ?? null, $dsl['mfdCount'] ?? null, $errors, 'mfdCount');
        if (!$isTecMode) {
            $this->validateRange('tubeCount', $config['tubeCount'] ?? null, $dsl['tubeCount'] ?? null, $errors, 'tubeCount');
        }

        $this->validateFiberCount($config, $fiberCount, $errors);
        $this->validateTubeCount($config, $fiberCount, $isTecMode, $errors);
        $this->validateConnectors($config, $isTecMode, $errors);
        if ($isTecMode) {
            $this->validateTecSleeves($config, $errors);
            $this->validateTecTubeArraySize($config, $errors);
        }
        $this->validateTubesStartPosition($config, $fiberCount, $mfdCount, $isTecMode, $errors);

        return [
            'derived' => [
                'fiberCount' => $fiberCount,
                'processType' => $processType,
            ],
            'errors' => $errors,
        ];
    }

    private function validateRange(string $name, mixed $value, mixed $rule, array &$errors, string $path): void
    {
        if (!is_array($rule)) return;
        if (!is_numeric($value)) return;

        $min = $rule['min'] ?? null;
        $max = $rule['max'] ?? null;
        $v = (float)$value;

        if ($min !== null && is_numeric($min) && $v < (float)$min) {
            $errors[] = ['path' => $path, 'message' => "{$name}は".(float)$min."以上です"];
        }
        if ($max !== null && is_numeric($max) && $v > (float)$max) {
            $errors[] = ['path' => $path, 'message' => "{$name}は".(float)$max."以下です"];
        }
    }

    private function validateFiberCount(array $config, int $fiberCount, array &$errors): void
    {
        $fibers = $config['fibers'] ?? [];
        if (!is_array($fibers) || count($fibers) !== $fiberCount) {
            $errors[] = ['path' => 'fibers', 'message' => 'fibers配列の個数が不正です'];
        }
    }

    private function validateTubeCount(array $config, int $fiberCount, bool $isTecMode, array &$errors): void
    {
        $tubeCountRaw = $config['tubeCount'] ?? null;
        if (!is_numeric($tubeCountRaw)) {
            return;
        }

        $tubeCount = (int)$tubeCountRaw;
        if ($isTecMode) {
            if ($tubeCount < 0 || $tubeCount > 1) {
                $errors[] = ['path' => 'tubeCount', 'message' => 'TECモードのtubeCountは0または1です'];
            }
            return;
        }

        if ($tubeCount < 0 || $tubeCount > $fiberCount) {
            $errors[] = ['path' => 'tubeCount', 'message' => "tubeCountは0〜{$fiberCount}です"];
        }
    }

    private function validateConnectors(array $config, bool $isTecMode, array &$errors): void
    {
        $connectors = $config['connectors'] ?? [];
        if (!is_array($connectors)) {
            return;
        }
        $mode = strtolower(trim((string)($connectors['mode'] ?? 'none')));
        $allowed = $isTecMode ? ['none', 'left', 'right'] : ['none', 'left', 'right', 'both'];
        if (!in_array($mode, $allowed, true)) {
            $errors[] = ['path' => 'connectors.mode', 'message' => $isTecMode
                ? 'TECモードのconnectors.modeは none / left / right のみです'
                : 'connectors.modeが不正です'];
            return;
        }
        if ($isTecMode && $mode === 'both') {
            $errors[] = ['path' => 'connectors.mode', 'message' => 'TECモードではconnectors.mode=bothは選択できません'];
        }
    }

    private function validateTecSleeves(array $config, array &$errors): void
    {
        $sleeves = $config['sleeves'] ?? [];
        if (!is_array($sleeves)) {
            return;
        }
        if (count($sleeves) > 0) {
            $errors[] = ['path' => 'sleeves', 'message' => 'TECモードではsleevesを設定できません'];
        }
    }

    private function validateTecTubeArraySize(array $config, array &$errors): void
    {
        $tubes = $config['tubes'] ?? [];
        if (!is_array($tubes)) {
            return;
        }
        if (count($tubes) > 1) {
            $errors[] = ['path' => 'tubes', 'message' => 'TECモードではtubesは最大1件です'];
        }
    }

    private function normalizeProcessType(mixed $raw): ?string
    {
        $value = strtoupper(trim((string)$raw));
        if (in_array($value, ['MFD', 'TEC20', 'TEC30', 'TEC20_HP', 'TEC30_HP'], true)) {
            return $value;
        }

        return null;
    }

    /**
     * チューブ開始位置のエラー判定（path設計を含む）
     * @return array<int, array{path:string,message:string}>
     */
    private function validateTubesStartPosition(array $config, int $fiberCount, int $mfdCount, bool $isTecMode, array &$errors): void
    {
        $mfdCount = max(1, min(10, $mfdCount));

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
        if (!is_array($tubes)) return;

        foreach ($tubes as $j => $tube) {
            if (!is_array($tube)) {
                continue;
            }
            $aIdx = 0;
            if (!$isTecMode) {
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
            }

            // 2) startOffsetM（±m）
            $offset = $this->extractLengthM($tube, 'startOffsetM', 'startOffsetMm');
            if (!is_numeric($offset)) {
                $errors[] = ['path' => "tubes.$j.startOffsetM", 'message' => 'startOffsetM（±m）が数値ではありません'];
                continue;
            }
            $offset = (float)$offset;

            // 3) 新方式: start/end ファイバ指定がある場合
            $startIdx = $tube['startFiberIndex'] ?? null;
            $endIdx = $tube['endFiberIndex'] ?? null;
            $endOffset = $this->extractLengthM($tube, 'endOffsetM', 'endOffsetMm');
            if (is_numeric($startIdx) || is_numeric($endIdx) || $endOffset !== null) {
                if (!is_numeric($startIdx)) {
                    $errors[] = ['path' => "tubes.$j.startFiberIndex", 'message' => 'startFiberIndexが数値ではありません'];
                    continue;
                }
                if (!is_numeric($endIdx)) {
                    $errors[] = ['path' => "tubes.$j.endFiberIndex", 'message' => 'endFiberIndexが数値ではありません'];
                    continue;
                }
                if (!is_numeric($endOffset)) {
                    $errors[] = ['path' => "tubes.$j.endOffsetM", 'message' => 'endOffsetMが数値ではありません'];
                    continue;
                }

                $si = (int)$startIdx;
                $ei = (int)$endIdx;
                if ($si < 0 || $si >= $fiberCount) {
                    $errors[] = ['path' => "tubes.$j.startFiberIndex", 'message' => "startFiberIndexは0〜".($fiberCount-1)."です"];
                    continue;
                }
                if ($ei < 0 || $ei >= $fiberCount) {
                    $errors[] = ['path' => "tubes.$j.endFiberIndex", 'message' => "endFiberIndexは0〜".($fiberCount-1)."です"];
                    continue;
                }

                $startOffset = $offset;
                $endOffset = (float)$endOffset;
                $startSegLen = $segLens[$si] ?? $fallbackPerSeg;
                $endSegLen = $segLens[$ei] ?? $fallbackPerSeg;

                if ($startOffset < 0 || $startOffset > $startSegLen) {
                    $errors[] = ['path' => "tubes.$j.startOffsetM", 'message' => "開始位置が範囲外です（0〜{$startSegLen}m）"];
                }
                if ($endOffset < 0 || $endOffset > $endSegLen) {
                    $errors[] = ['path' => "tubes.$j.endOffsetM", 'message' => "終了位置が範囲外です（0〜{$endSegLen}m）"];
                }

                $startAbs = ($segLens[0] ?? 0) * 0.0;
                $endAbs = ($segLens[0] ?? 0) * 0.0;
                $cum = 0.0;
                for ($i = 0; $i < $fiberCount; $i++) {
                    if ($i === $si) {
                        $startAbs = $cum + $startOffset;
                    }
                    if ($i === $ei) {
                        $endAbs = $cum + $endOffset;
                    }
                    $cum += $segLens[$i] ?? 0.0;
                }
                if ($endAbs < $startAbs) {
                    $errors[] = ['path' => "tubes.$j.endOffsetM", 'message' => '終了位置が開始位置より左です'];
                }
                continue;
            }

            // 3) 旧方式: lengthM（チューブ長）
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

            // 4) targetFiberIndex がある場合は、そのファイバー区間を基準に判定（描画と整合）
            $targetIdx = $tube['targetFiberIndex'] ?? null;
            if (is_numeric($targetIdx)) {
                $ti = (int)$targetIdx;
                if ($ti >= 0 && $ti < $fiberCount) {
                    $segLen = $segLens[$ti] ?? $fallbackPerSeg;
                    // 描画側の挙動に合わせて開始位置はクランプ
                    $startM = max(0.0, min($segLen, $offset));
                    $endM = $startM + $lenM;

                    if ($endM < 0 || $endM > $segLen) {
                        $errors[] = ['path' => "tubes.$j.lengthM", 'message' => "終了位置が範囲外です（0〜{$segLen}m）"];
                    }
                    continue;
                }
            }

            if ($isTecMode) {
                $segLen = $segLens[0] ?? $fallbackPerSeg;
                $startM = max(0.0, min($segLen, $offset));
                $endM = $startM + $lenM;
                if ($endM < 0 || $endM > $segLen) {
                    $errors[] = ['path' => "tubes.$j.lengthM", 'message' => "終了位置が範囲外です（0〜{$segLen}m）"];
                }
                continue;
            }

            // 開始・終了（m）: anchor（MFD）基準（targetFiberIndexが不正な場合のみ）
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
}
