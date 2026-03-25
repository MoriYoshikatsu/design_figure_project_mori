<?php

namespace App\Services;

final class DslEngine
{
    private const PROCESS_TYPE_MFD = 'MFD';
    private const PROCESS_TYPE_TEC = 'TEC';
    /** @var array<int, string> */
    private const TEC_PROCESS_TYPES = ['TEC20', 'TEC30', 'TEC20_HP', 'TEC30_HP'];
    private const MIN_LENGTH_M = 0.2;
    private const MAX_LENGTH_M = 2.0;
    private const FIBER_LENGTH_STEP_M = 0.1;
    private const TUBE_LENGTH_STEP_M = 0.01;
    private const FIXED_MFD_COUNT = 1;
    private const FIXED_TEC_FIBER_COUNT = 1;
    private const MAX_TUBE_COUNT = 2;

    /**
     * @return array{derived: array, errors: array}
     */
    public function evaluate(array $config, array $dsl): array
    {
        $errors = [];

        $processType = $this->normalizeProcessType($config['processType'] ?? self::PROCESS_TYPE_MFD);
        if ($processType === null) {
            $errors[] = ['path' => 'processType', 'message' => 'processTypeは MFD / TEC / TEC20 / TEC30 / TEC20_HP / TEC30_HP のいずれかです'];
            $processType = self::PROCESS_TYPE_MFD;
        }
        $isTecMode = $processType !== self::PROCESS_TYPE_MFD;
        $mfdCount = self::FIXED_MFD_COUNT;
        $fiberCount = $this->resolveRequiredFiberCount($isTecMode, $mfdCount);
        $tecSide = $this->normalizeTecSide($config['tecSide'] ?? null);
        if ($isTecMode && $tecSide === null) {
            $errors[] = ['path' => 'tecSide', 'message' => 'TECモードではtecSide（left/right/both）の指定が必須です'];
        }
        $tecProcessSelections = $this->resolveTecProcessSelections($config, $processType, $tecSide);
        if ($isTecMode) {
            if ($tecSide === 'left' || $tecSide === 'both') {
                if (($tecProcessSelections['left'] ?? null) === null) {
                    $errors[] = ['path' => 'tecLeftProcessType', 'message' => '左端TEC種別を選択してください'];
                }
            }
            if ($tecSide === 'right' || $tecSide === 'both') {
                if (($tecProcessSelections['right'] ?? null) === null) {
                    $errors[] = ['path' => 'tecRightProcessType', 'message' => '右端TEC種別を選択してください'];
                }
            }
        }

        $this->validateFiberCount($config, $fiberCount, $errors);
        $this->validateFiberSelections($config, $fiberCount, $errors);
        $this->validateFiberLengths($config, $errors);
        $this->validateTubeCount($config, $fiberCount, $isTecMode, $errors);
        $this->validateTubeSelections($config, $errors);
        $this->validateConnectors($config, $isTecMode, $errors);
        $this->validateTubeArraySize($config, $errors);
        if ($isTecMode) {
            $this->validateTecSleeves($config, $errors);
        }
        $this->validateTubesStartPosition($config, $fiberCount, $mfdCount, $isTecMode, $errors);

        return [
            'derived' => [
                'fiberCount' => $fiberCount,
                'processType' => $processType,
                'tecSide' => $tecSide,
                'tecLeftProcessType' => $tecProcessSelections['left'] ?? null,
                'tecRightProcessType' => $tecProcessSelections['right'] ?? null,
            ],
            'errors' => $errors,
        ];
    }

    private function resolveRequiredFiberCount(bool $isTecMode, int $mfdCount): int
    {
        if ($isTecMode) {
            return self::FIXED_TEC_FIBER_COUNT;
        }

        return max(1, $mfdCount + 1);
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
        if ($tubeCount < 0 || $tubeCount > self::MAX_TUBE_COUNT) {
            $errors[] = ['path' => 'tubeCount', 'message' => 'tubeCountは0〜2です'];
        }
    }

    private function validateConnectors(array $config, bool $isTecMode, array &$errors): void
    {
        $connectors = $config['connectors'] ?? [];
        if (!is_array($connectors)) {
            $errors[] = ['path' => 'connectors.mode', 'message' => 'コネクタを選択してください'];
            return;
        }
        $mode = strtolower(trim((string)($connectors['mode'] ?? 'none')));
        $allowed = ['none', 'left', 'right', 'both'];
        if (!in_array($mode, $allowed, true)) {
            $errors[] = ['path' => 'connectors.mode', 'message' => 'connectors.modeが不正です'];
            return;
        }
        if ($mode === 'none') {
            $left = trim((string)($connectors['leftSkuCode'] ?? ''));
            $right = trim((string)($connectors['rightSkuCode'] ?? ''));
            if ($left === '' && $right === '') {
                $errors[] = ['path' => 'connectors.mode', 'message' => 'コネクタを選択してください'];
            }
            return;
        }

        if (in_array($mode, ['left', 'both'], true) && trim((string)($connectors['leftSkuCode'] ?? '')) === '') {
            $errors[] = ['path' => 'connectors.leftSkuCode', 'message' => '左側コネクタを選択してください'];
        }
        if (in_array($mode, ['right', 'both'], true) && trim((string)($connectors['rightSkuCode'] ?? '')) === '') {
            $errors[] = ['path' => 'connectors.rightSkuCode', 'message' => '右側コネクタを選択してください'];
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

    private function validateTubeArraySize(array $config, array &$errors): void
    {
        $tubes = $config['tubes'] ?? [];
        if (!is_array($tubes)) {
            return;
        }
        if (count($tubes) > self::MAX_TUBE_COUNT) {
            $errors[] = ['path' => 'tubes', 'message' => 'tubesは最大2件です'];
        }
    }

    private function validateFiberLengths(array $config, array &$errors): void
    {
        $fibers = $config['fibers'] ?? [];
        if (!is_array($fibers)) {
            return;
        }

        foreach ($fibers as $i => $fiber) {
            if (!is_array($fiber)) {
                continue;
            }
            $len = $this->extractLengthM($fiber, 'lengthM', 'lengthMm');
            if (!is_numeric($len)) {
                $errors[] = ['path' => "fibers.$i.lengthM", 'message' => 'ファイバ長さが数値ではありません'];
                continue;
            }
            $len = (float)$len;
            if ($len < self::MIN_LENGTH_M || $len > self::MAX_LENGTH_M) {
                $errors[] = ['path' => "fibers.$i.lengthM", 'message' => 'ファイバ長さは0.2〜2.0mです'];
            }
            if (!$this->isStepAligned($len, self::FIBER_LENGTH_STEP_M)) {
                $errors[] = ['path' => "fibers.$i.lengthM", 'message' => 'ファイバ長さは0.1m刻みで入力してください'];
            }
        }
    }

    private function validateFiberSelections(array $config, int $fiberCount, array &$errors): void
    {
        $fibers = $config['fibers'] ?? [];
        if (!is_array($fibers)) {
            for ($i = 0; $i < $fiberCount; $i++) {
                $errors[] = ['path' => "fibers.$i.skuCode", 'message' => 'ファイバを選択してください'];
            }
            return;
        }

        for ($i = 0; $i < $fiberCount; $i++) {
            $fiber = $fibers[$i] ?? null;
            if (!is_array($fiber) || trim((string)($fiber['skuCode'] ?? '')) === '') {
                $errors[] = ['path' => "fibers.$i.skuCode", 'message' => 'ファイバを選択してください'];
            }
        }
    }

    private function validateTubeSelections(array $config, array &$errors): void
    {
        $tubeCount = is_numeric($config['tubeCount'] ?? null) ? (int)$config['tubeCount'] : 0;
        if ($tubeCount <= 0) {
            $errors[] = ['path' => 'tubeCount', 'message' => 'チューブを選択してください'];
            return;
        }

        $tubes = $config['tubes'] ?? [];
        if (!is_array($tubes)) {
            for ($i = 0; $i < $tubeCount; $i++) {
                $errors[] = ['path' => "tubes.$i.skuCode", 'message' => 'チューブを選択してください'];
            }
            return;
        }

        for ($i = 0; $i < $tubeCount; $i++) {
            $tube = $tubes[$i] ?? null;
            if (!is_array($tube) || trim((string)($tube['skuCode'] ?? '')) === '') {
                $errors[] = ['path' => "tubes.$i.skuCode", 'message' => 'チューブを選択してください'];
            }
        }
    }

    private function normalizeProcessType(mixed $raw): ?string
    {
        $value = strtoupper(trim((string)$raw));
        if (in_array($value, array_merge([self::PROCESS_TYPE_MFD, self::PROCESS_TYPE_TEC], self::TEC_PROCESS_TYPES), true)) {
            return $value;
        }

        return null;
    }

    private function normalizeTecSide(mixed $raw): ?string
    {
        $side = strtolower(trim((string)$raw));
        if (!in_array($side, ['left', 'right', 'both'], true)) {
            return null;
        }

        return $side;
    }

    private function normalizeConcreteTecProcessType(mixed $raw): ?string
    {
        $value = strtoupper(trim((string)$raw));
        if (!in_array($value, self::TEC_PROCESS_TYPES, true)) {
            return null;
        }

        return $value;
    }

    /**
     * @return array{left:?string,right:?string}
     */
    private function resolveTecProcessSelections(array $config, string $processType, ?string $tecSide): array
    {
        $legacyTecType = $this->normalizeConcreteTecProcessType($processType);
        $leftType = $this->normalizeConcreteTecProcessType($config['tecLeftProcessType'] ?? null);
        $rightType = $this->normalizeConcreteTecProcessType($config['tecRightProcessType'] ?? null);

        if ($tecSide === 'left') {
            return ['left' => $leftType ?? $legacyTecType, 'right' => null];
        }
        if ($tecSide === 'right') {
            return ['left' => null, 'right' => $rightType ?? $legacyTecType];
        }
        if ($tecSide === 'both') {
            return ['left' => $leftType ?? $legacyTecType, 'right' => $rightType ?? $legacyTecType];
        }

        return ['left' => null, 'right' => null];
    }

    /**
     * チューブ開始位置のエラー判定（path設計を含む）
     * @return array<int, array{path:string,message:string}>
     */
    private function validateTubesStartPosition(array $config, int $fiberCount, int $mfdCount, bool $isTecMode, array &$errors): void
    {
        // fiber長さ（未入力に備えた暫定値）
        $fallbackPerSeg = self::MIN_LENGTH_M;
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
            if (!$this->isStepAligned($offset, self::TUBE_LENGTH_STEP_M)) {
                $errors[] = ['path' => "tubes.$j.startOffsetM", 'message' => 'チューブ位置は0.01m刻みで入力してください'];
            }

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
                if (!$this->isStepAligned($endOffset, self::TUBE_LENGTH_STEP_M)) {
                    $errors[] = ['path' => "tubes.$j.endOffsetM", 'message' => 'チューブ位置は0.01m刻みで入力してください'];
                }
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
                $tubeLen = $endAbs - $startAbs;
                if ($tubeLen < self::MIN_LENGTH_M || $tubeLen > self::MAX_LENGTH_M) {
                    $errors[] = ['path' => "tubes.$j.endOffsetM", 'message' => 'チューブ長さは0.2〜2.0mです'];
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
            if ($lenM < self::MIN_LENGTH_M || $lenM > self::MAX_LENGTH_M) {
                $errors[] = ['path' => "tubes.$j.lengthM", 'message' => 'チューブ長さは0.2〜2.0mです'];
                continue;
            }
            if (!$this->isStepAligned($lenM, self::TUBE_LENGTH_STEP_M)) {
                $errors[] = ['path' => "tubes.$j.lengthM", 'message' => 'チューブ長さは0.01m刻みで入力してください'];
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

    private function isStepAligned(float $value, float $step): bool
    {
        if ($step <= 0) {
            return true;
        }

        $scaled = $value / $step;
        return abs($scaled - round($scaled)) < 0.000001;
    }
}
