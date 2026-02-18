<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class SvgRenderer
{
    /**
     * @param array $config  config（構成データ）
     * @param array $derived derived（導出値）
     * @param array $errors  errors（検証エラー）
     */
    public function render(array $config, array $derived = [], array $errors = []): string
    {
        $targets = $this->collectErrorTargets($errors);

        $mfdCount = (int)($config['mfdCount'] ?? 1);
        $fibers   = $config['fibers'] ?? [];
        $tubes    = $config['tubes'] ?? [];
        $conns    = $config['connectors'] ?? ['mode' => 'both', 'leftSkuCode' => null, 'rightSkuCode' => null];
        $sleeves  = $config['sleeves'] ?? [];
        $skuNameByCode = $derived['skuNameByCode'] ?? [];
        $skuSvgByCode = $derived['skuSvgByCode'] ?? [];
        if (empty($skuNameByCode)) {
            $skuNameByCode = $this->buildSkuNameMap();
        }
        if (empty($skuSvgByCode)) {
            $skuSvgByCode = $this->buildSkuSvgMap();
        }

        $fiberCount = (int)($derived['fiberCount'] ?? ($mfdCount + 1));
        if ($fiberCount < 1) $fiberCount = 1;

        // --- fiber長さ（m）を取得（未入力はnull）
        $fiberLens = [];
        for ($i = 0; $i < $fiberCount; $i++) {
            $len = $this->extractLengthM($fibers[$i] ?? [], 'lengthM', 'lengthMm');
            $fiberLens[$i] = is_numeric($len) ? (float)$len : null;
        }

        // 未入力がある場合もSVGが崩れないように、暫定で1区間0.1m相当を割り当て
        $fallbackPerSeg = 0.1;

        // 実長（actual）と表示用（display）の長さを分ける
        $actualSegmentLens = [];
        for ($i = 0; $i < $fiberCount; $i++) {
            $actualSegmentLens[$i] = ($fiberLens[$i] !== null && $fiberLens[$i] > 0) ? $fiberLens[$i] : $fallbackPerSeg;
        }

        $displaySegmentLens = $derived['displaySegmentLens'] ?? null;
        if (!is_array($displaySegmentLens) || count($displaySegmentLens) < $fiberCount) {
            $displaySegmentLens = $actualSegmentLens;
        } else {
            for ($i = 0; $i < $fiberCount; $i++) {
                $v = $displaySegmentLens[$i] ?? null;
                $displaySegmentLens[$i] = (is_numeric($v) && (float)$v > 0) ? (float)$v : $actualSegmentLens[$i];
            }
        }

        $totalLen = (float)($derived['totalLengthM'] ?? 0);
        if ($totalLen <= 0 && is_numeric($derived['totalLengthMm'] ?? null)) {
            $totalLen = (float)$derived['totalLengthMm'] / 1000;
        }
        if ($totalLen <= 0) {
            $totalLen = array_sum($displaySegmentLens);
        }
        if ($totalLen <= 0) $totalLen = array_sum($displaySegmentLens);

        // --- 区間の開始/終了（m）と MFDマーカー（display m）を計算
        $segStart = [];
        $segEnd   = [];
        $mfdPos   = []; // MFD[k]の位置（display m）
        $mfdActualPos = []; // MFD[k]の位置（actual m）

        $actualStart = [];
        $actualEnd   = [];
        $displayStart = [];
        $displayEnd   = [];

        $cumActual = 0.0;
        $cumDisplay = 0.0;
        for ($i = 0; $i < $fiberCount; $i++) {
            $actualStart[$i] = $cumActual;
            $cumActual += $actualSegmentLens[$i];
            $actualEnd[$i] = $cumActual;

            $displayStart[$i] = $cumDisplay;
            $cumDisplay += $displaySegmentLens[$i];
            $displayEnd[$i] = $cumDisplay;

            $segStart[$i] = $displayStart[$i];
            $segEnd[$i] = $displayEnd[$i];

            // MFD[k] は fiber[k] の終端（actual m）
            if ($i < $mfdCount) {
                $mfdActualPos[$i] = $actualEnd[$i];
            }
        }

        $mapM = function (float $m) use ($fiberCount, $actualStart, $actualEnd, $displayStart, $displayEnd, $actualSegmentLens, $displaySegmentLens): float {
            if ($m <= 0) return 0.0;
            for ($i = 0; $i < $fiberCount; $i++) {
                if ($m <= $actualEnd[$i]) {
                    $segActual = $actualSegmentLens[$i] ?: 1.0;
                    $segDisplay = $displaySegmentLens[$i] ?: 1.0;
                    $ratio = $segDisplay / $segActual;
                    return $displayStart[$i] + ($m - $actualStart[$i]) * $ratio;
                }
            }
            return $displayEnd[$fiberCount - 1] ?? 0.0;
        };

        for ($k = 0; $k < $mfdCount; $k++) {
            if (!array_key_exists($k, $mfdActualPos)) continue;
            $mfdPos[$k] = $mapM((float)$mfdActualPos[$k]);
        }

        // --- SVGレイアウト（px）
        $width  = 1000;
        $height = 250;
        $margin = 80;

        $axisY      = 140; // fiberの中心Y
        $fiberH     = 4;   // 表示図形は細く
        $fiberSvgH  = $fiberH / 2;  // 画像も細く
        $tubeH      = $fiberH; // fiberより少し太い
        $tubeY      = $axisY - ($tubeH / 2); // fiberを包む
        $connW      = 30;
        $connH      = 20; // 表示図形は細く
        $connTipW   = 6;
        $connTipH   = 6;
        $connBodyW  = $connW - $connTipW;
        $connSvgW   = $connW * 2;
        $connSvgH   = 36 * 2; // 既存の36を基準にさらに2倍
        // ラベル配置（ファイバ中心を基準に上下階層）
        // 下側: (1)ファイバ寸法 (2)ファイバSKU
        // 上側: (1)チューブ寸法/開始位置 (2)チューブSKU (3)MFD変換SKU/コネクタSKU
        $belowDimY = $axisY + 12;
        $belowLabelY = $belowDimY + 18;
        $belowLabelY2 = $belowLabelY + 18;
        $belowLabelY3 = $belowLabelY2 + 18;

        $aboveDimY = $axisY - 12;
        $aboveOffsetDimY = $aboveDimY - 18;
        $tubeLabelY = $aboveDimY - 42;
        $mfdLabelY  = $tubeLabelY - 24;
        $labelY     = $belowLabelY2;
        $connLabelY = $belowLabelY3;

        $dense = $fiberCount >= 6 || $mfdCount >= 6;
        $labelSize = $dense ? 12 : 13;
        $smallSize = $dense ? 11 : 12;

        $usableW = $width - 2 * $margin;
        $scale   = ($totalLen > 0) ? ($usableW / $totalLen) : 1.0;

        // 文字列エスケープ（SVG/XML安全）
        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1);

        // --- SVG開始
        $svg = [];
        $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="xMinYMin meet" style="width:100%; height:auto; display:block; overflow:visible;">';
        $illustrationHref = $derived['illustration'] ?? null;
        if ($illustrationHref) {
            $svg[] = '<image href="'.$esc($illustrationHref).'" x="0" y="40" width="'.$width.'" height="200" opacity="0.18" />';
        }
        $svg[] = '<style>
            .fiber { fill:#e5e7eb; stroke:#374151; stroke-width:1; }
            .fiber.unknown { stroke-dasharray:4 2; fill:#f3f4f6; }
            .tube { fill:none; stroke:#facc15; stroke-width:2; opacity:0.95; }
            .marker { stroke:#111827; stroke-width:2; }
            .conn { fill:#d1d5db; stroke:#374151; stroke-width:1; }
            .label { font-size:'.$labelSize.'px; fill:#111827; font-weight:700; font-family: ui-sans-serif, system-ui, -apple-system; }
            .small { font-size:'.$smallSize.'px; fill:#1f2937; font-weight:600; }
            .dim { stroke:#111827; stroke-width:1.5; }
            .err { stroke:#dc2626 !important; fill:#fecaca !important; }
            .errText { fill:#dc2626; font-weight:800; }
        </style>';
        $svg[] = '<defs></defs>';

        // ヘッダ（情報）
        $sleeveNameList = [];
        for ($k = 0; $k < $mfdCount; $k++) {
            $code = $sleeves[$k]['skuCode'] ?? null;
            $name = $code ? ($skuNameByCode[$code] ?? null) : null;
            $sleeveNameList[] = $name ? ('MFD['.$k.'] '.$name) : ('MFD['.$k.'] (not set)');
        }
        $svg[] = '<text x="'.$margin.'" y="28" class="label'.($targets['sleeve'] ? ' errText' : '').'">'
              . 'MFD変換の数: '.$esc($mfdCount).' / ファイバーの数: '.$esc($fiberCount)
            //   . ' / Sleeves: '.$esc(implode(' / ', $sleeveNameList))
              . '</text>';

        // 軸（ベースライン）
        $svg[] = '<line x1="'.$margin.'" y1="'.$axisY.'" x2="'.($width-$margin).'" y2="'.$axisY.'" stroke="#9ca3af" stroke-width="1" />';

        $connMode = $conns['mode'] ?? 'both';
        $showLeft = in_array($connMode, ['left', 'both'], true);
        $showRight = in_array($connMode, ['right', 'both'], true);

        $showFiberDims = $fiberCount <= 4;
        $showTubeDims = true;

        // --- fiber区間
        $segmentIllustrations = $derived['segmentIllustrations'] ?? [];

        for ($i = 0; $i < $fiberCount; $i++) {
            $x = $margin + $segStart[$i] * $scale;
            $w = max(1.0, $displaySegmentLens[$i] * $scale);
            $y = $axisY - $fiberH / 2;

            $unknown = ($fiberLens[$i] === null || $fiberLens[$i] <= 0);
            $cls = 'fiber'
                . ($unknown ? ' unknown' : '')
                . (in_array($i, $targets['fiberIdx'], true) || $targets['fibersAll'] ? ' err' : '');

            $skuCode = $fibers[$i]['skuCode'] ?? null;
            $fiberSvg = $skuCode ? ($skuSvgByCode[$skuCode] ?? null) : null;
            if (!$fiberSvg) {
                $svg[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$fiberH.'" class="'.$cls.'" id="fiber-'.$i.'" data-path="fibers.'.$i.'" />';
            }
            if ($fiberSvg) {
                // 線系SVGは比率維持だと幅が縮むため、PDFでも端まで届くようにストレッチ
                $svg[] = '<image href="'.$esc($fiberSvg).'" x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$fiberSvgH.'" opacity="0.9" preserveAspectRatio="none" class="fiber-img" />';
            }

            if ($showFiberDims) {
                $dimY = $belowDimY;
                $svg[] = '<line x1="'.$x.'" y1="'.$dimY.'" x2="'.($x+$w).'" y2="'.$dimY.'" class="dim" />';
                $svg[] = $this->arrowHead($x, $dimY, true);
                $svg[] = $this->arrowHead($x + $w, $dimY, false);
                $segLen = $actualSegmentLens[$i] ?? null;
                $tolM = $this->extractLengthM($fibers[$i] ?? [], 'toleranceM', 'toleranceMm');
                $tolTxt = (is_numeric($tolM)) ? ' ± '.$tolM.'m' : '';
                $svg[] = '<text x="'.($x + $w / 2).'" y="'.($dimY + 12).'" class="small" text-anchor="middle">'. $esc(($segLen !== null ? $segLen : '?').'m'.$tolTxt) .'</text>';
            }

            if (!empty($segmentIllustrations[$i])) {
                $imgW = max(12.0, min(160.0, $w - 6.0));
                $imgH = min(18.0, $fiberH - 4.0);
                $imgX = $x + ($w - $imgW) / 2.0;
                $imgY = $y + ($fiberH - $imgH) / 2.0;
                $svg[] = '<image href="'.$esc($segmentIllustrations[$i]).'" x="'.$imgX.'" y="'.$imgY.'" width="'.$imgW.'" height="'.$imgH.'" opacity="0.9" />';
            }

            // ラベル（長さ/誤差）
            $sku = $skuCode ? ($skuNameByCode[$skuCode] ?? null) : null;
            $txt = 'F['.$i.']:　'.($sku ?? '選択してください');
            $svg[] = '<text x="'.($x + $w / 2).'" y="'.$labelY.'" class="small'.(in_array($i, $targets['fiberIdx'], true) ? ' errText' : '').'" text-anchor="middle">'
                . $esc($txt).'</text>';
        }

        // --- tubes（fiber順に一致させて描画）: 最前面に描画
        $tubeCount = count($tubes);
        $maxTubeIdx = min($fiberCount, $tubeCount);

        for ($j = 0; $j < $maxTubeIdx; $j++) {
            $targetIdx = $tubes[$j]['targetFiberIndex'] ?? $j;
            if (!is_numeric($targetIdx)) $targetIdx = $j;
            $targetIdx = (int)$targetIdx;
            if ($targetIdx < 0 || $targetIdx >= $fiberCount) {
                continue;
            }

            $segActualLen = $actualSegmentLens[$targetIdx] ?? 0.0;
            $segDisplayLen = $displaySegmentLens[$targetIdx] ?? 0.0;
            $ratio = ($segActualLen > 0) ? ($segDisplayLen / $segActualLen) : 0.0;
            $startM = 0.0;

            $offsetM = $this->extractLengthM($tubes[$j] ?? [], 'startOffsetM', 'startOffsetMm') ?? 0.0;
            $lenM = $this->extractLengthM($tubes[$j] ?? [], 'lengthM', 'lengthMm') ?? 0.0;

            // 新方式: start/end ファイバ指定があればそれを優先
            $startFiberIdx = is_numeric($tubes[$j]['startFiberIndex'] ?? null) ? (int)$tubes[$j]['startFiberIndex'] : null;
            $endFiberIdx = is_numeric($tubes[$j]['endFiberIndex'] ?? null) ? (int)$tubes[$j]['endFiberIndex'] : null;
            $endOffsetM = $this->extractLengthM($tubes[$j] ?? [], 'endOffsetM', 'endOffsetMm');

            if ($startFiberIdx !== null && $endFiberIdx !== null && $endOffsetM !== null
                && $startFiberIdx >= 0 && $startFiberIdx < $fiberCount
                && $endFiberIdx >= 0 && $endFiberIdx < $fiberCount) {
                $startSegLen = $actualSegmentLens[$startFiberIdx] ?? 0.0;
                $endSegLen = $actualSegmentLens[$endFiberIdx] ?? 0.0;
                $startOffset = max(0.0, min($startSegLen, $offsetM));
                $endOffset = max(0.0, min($endSegLen, $endOffsetM));

                $startActual = ($actualStart[$startFiberIdx] ?? 0.0) + $startOffset;
                $endActual = ($actualStart[$endFiberIdx] ?? 0.0) + $endOffset;
                if ($endActual < $startActual) {
                    $tmp = $endActual;
                    $endActual = $startActual;
                    $startActual = $tmp;
                }

                $startDisplay = $mapM($startActual);
                $endDisplay = $mapM($endActual);

                $x = $margin + $startDisplay * $scale;
                $w = max(1.0, ($endDisplay - $startDisplay) * $scale);
                $lenM = max(0.0, $endActual - $startActual);
                $targetIdx = $startFiberIdx;
                $startM = $startOffset;
                $segActualLen = $actualSegmentLens[$targetIdx] ?? 0.0;
                $segDisplayLen = $displaySegmentLens[$targetIdx] ?? 0.0;
                $ratio = ($segActualLen > 0) ? ($segDisplayLen / $segActualLen) : 0.0;
            } else {
                $startM = max(0.0, min($segActualLen, $offsetM));
                $endM = max($startM, min($segActualLen, $startM + max(0.0, $lenM)));
                $x = $margin + ($segStart[$targetIdx] + ($startM * $ratio)) * $scale;
                $w = max(1.0, ($endM - $startM) * $ratio * $scale);
            }
            $y = $tubeY;

            $cls = 'tube'
                . (in_array((int)$j, $targets['tubeIdx'], true) || $targets['tubesAll'] ? ' err' : '');

            $skuCode = $tubes[$j]['skuCode'] ?? null;
            $tubeSvg = $skuCode ? ($skuSvgByCode[$skuCode] ?? null) : null;
            if (!$tubeSvg) {
                $svg[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$tubeH.'" class="'.$cls.'" id="tube-'.$j.'" data-path="tubes.'.$j.'" />';
            }
            if ($tubeSvg) {
                $svg[] = '<image href="'.$esc($tubeSvg).'" x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$tubeH.'" opacity="0.9" preserveAspectRatio="xMidYMid meet" />';
            }

            if ($showTubeDims) {
                $dimY = $aboveDimY;
                $svg[] = '<line x1="'.$x.'" y1="'.$dimY.'" x2="'.($x+$w).'" y2="'.$dimY.'" class="dim" />';
                $svg[] = $this->arrowHead($x, $dimY, true);
                $svg[] = $this->arrowHead($x + $w, $dimY, false);
                $tolM = $this->extractLengthM($tubes[$j] ?? [], 'toleranceM', 'toleranceMm');
                $tolTxt = (is_numeric($tolM)) ? ' ± '.$tolM.'m' : '';
                $svg[] = '<text x="'.($x + $w / 2).'" y="'.($dimY - 4).'" class="small" text-anchor="middle">'. $esc($lenM.'m'.$tolTxt) .'</text>';

                // 左端開始距離（ファイバ左端→チューブ左端）
                if ($offsetM > 0) {
                    $segX = $margin + $segStart[$targetIdx] * $scale;
                    $offsetW = max(1.0, $startM * $ratio * $scale);
                    $offsetY = $aboveOffsetDimY;
                    $svg[] = '<line x1="'.$segX.'" y1="'.$offsetY.'" x2="'.($segX + $offsetW).'" y2="'.$offsetY.'" class="dim" />';
                    $svg[] = $this->arrowHead($segX, $offsetY, true);
                    $svg[] = $this->arrowHead($segX + $offsetW, $offsetY, false);
                    $svg[] = '<text x="'.($segX + $offsetW / 2).'" y="'.($offsetY - 4).'" class="small" text-anchor="middle">'. $esc($offsetM.'m') .'</text>';
                }
            }

            $sku = $skuCode ? ($skuNameByCode[$skuCode] ?? null) : null;
            if ($startFiberIdx !== null && $endFiberIdx !== null) {
                $txt = 'T['.$j.']:　'.($sku ?? '選択してください').'　=>　F['.$startFiberIdx.']~F['.$endFiberIdx.']';
            } else {
                $txt = 'T['.$j.']:　'.($sku ?? '選択してください').'　=>　F['.$targetIdx.']';
            }
            $svg[] = '<text x="'.($x + $w / 2).'" y="'.$tubeLabelY.'" class="small'.(in_array((int)$j, $targets['tubeIdx'], true) ? ' errText' : '').'" text-anchor="middle">'
                . $esc($txt).'</text>';
        }

        // --- MFDマーカー
        for ($k = 0; $k < $mfdCount; $k++) {
            $m = $mfdPos[$k] ?? null;
            if ($m === null) continue;

            $x = $margin + $m * $scale;
            $cls = 'marker'.($targets['mfd'] ? ' err' : '');
            $svg[] = '<line x1="'.$x.'" y1="'.($axisY-36).'" x2="'.$x.'" y2="'.($axisY+36).'" class="'.$cls.'" id="mfd-'.$k.'" data-path="mfd.'.$k.'" stroke-dasharray="4 4" stroke="#9ca3af" opacity="0.7" />';
            $sleeveCode = $sleeves[$k]['skuCode'] ?? null;
            $sleeveName = $sleeveCode ? ($skuNameByCode[$sleeveCode] ?? null) : null;
            $mfdLabel = $sleeveName ? ('MFD['.$k.']: '.$sleeveName) : ('MFD['.$k.']');
            $svg[] = '<text x="'.$x.'" y="'.$mfdLabelY.'" class="small'.($targets['mfd'] ? ' errText' : '').'" text-anchor="middle">'.$esc($mfdLabel).'</text>';
            $sleeveSvg = $sleeveCode ? ($skuSvgByCode[$sleeveCode] ?? null) : null;
            if ($sleeveSvg) {
                $sleeveW = 72;
                $sleeveH = 72;
                $sx = $x - ($sleeveW / 2);
                $sy = $axisY - ($sleeveH / 2);
                // 背面が透けないように下地を敷く
                // $svg[] = '<rect x="'.$sx.'" y="'.$sy.'" width="'.$sleeveW.'" height="'.$sleeveH.'" fill="#d1d5db" />';
                $svg[] = '<image href="'.$esc($sleeveSvg).'" x="'.$sx.'" y="'.$sy.'" width="'.$sleeveW.'" height="'.$sleeveH.'" opacity="1.0" preserveAspectRatio="xMidYMid meet" />';
            }
        }

        // --- コネクタ（左）
        if ($showLeft && !empty($conns['leftSkuCode'])) {
            $leftName = $skuNameByCode[$conns['leftSkuCode']] ?? null;
            $leftSvg = $skuSvgByCode[$conns['leftSkuCode']] ?? null;
            $x = $margin - $connW;
            $y = $axisY - ($connH / 2);
            $cls = 'conn'.($targets['connLeft'] ? ' err' : '');
            $tipY = $y + ($connH - $connTipH) / 2;
            $bodyX = $x + $connTipW;
            if ($leftSvg) {
                $imgX = $margin - $connSvgW; // 右端がファイバ先端に一致
                $imgY = $axisY - ($connSvgH / 2);
                $svg[] = '<image href="'.$esc($leftSvg).'" x="'.$imgX.'" y="'.$imgY.'" width="'.$connSvgW.'" height="'.$connSvgH.'" opacity="0.95" preserveAspectRatio="xMidYMid meet" />';
            } else {
                $svg[] = '<rect x="'.$bodyX.'" y="'.$y.'" width="'.$connBodyW.'" height="'.$connH.'" rx="3" class="'.$cls.'" id="conn-left" />';
                $svg[] = '<rect x="'.$x.'" y="'.$tipY.'" width="'.$connTipW.'" height="'.$connTipH.'" rx="2" class="'.$cls.'" />';
            }
            $labelX = max(4, $x);
            $svg[] = '<text x="'.$labelX.'" y="'.$connLabelY.'" class="small" text-anchor="start">'. $esc($leftName ?? '') .'</text>';
        }

        // --- コネクタ（右）
        $rightSku = $conns['rightSkuCode'] ?? null;
        if ($showRight && !empty($rightSku)) {
            $rightName = $skuNameByCode[$rightSku] ?? null;
            $rightSvg = $skuSvgByCode[$rightSku] ?? null;
            $x = $margin + $totalLen * $scale;
            $y = $axisY - ($connH / 2);
            $cls = 'conn'.($targets['connRight'] ? ' err' : '');
            $tipY = $y + ($connH - $connTipH) / 2;
            $bodyX = $x;
            $tipX = $x + $connBodyW;
            if ($rightSvg) {
                $imgX = $margin + $totalLen * $scale; // 左端がファイバ先端に一致
                $imgY = $axisY - ($connSvgH / 2);
                $svg[] = '<image href="'.$esc($rightSvg).'" x="'.(-($imgX + $connSvgW)).'" y="'.$imgY.'" width="'.$connSvgW.'" height="'.$connSvgH.'" opacity="0.95" preserveAspectRatio="xMidYMid meet" transform="scale(-1,1)" />';
            } else {
                $svg[] = '<rect x="'.$bodyX.'" y="'.$y.'" width="'.$connBodyW.'" height="'.$connH.'" rx="3" class="'.$cls.'" id="conn-right" />';
                $svg[] = '<rect x="'.$tipX.'" y="'.$tipY.'" width="'.$connTipW.'" height="'.$connTipH.'" rx="2" class="'.$cls.'" />';
            }
            $labelX = min($width - 4, $x + $connW);
            $svg[] = '<text x="'.$labelX.'" y="'.$connLabelY.'" class="small" text-anchor="end">'. $esc($rightName ?? '') .'</text>';
        }

        $svg[] = '</svg>';
        return implode("\n", $svg);
    }

    private function arrowHead(float $x, float $y, bool $left): string
    {
        $size = 6;
        $half = 3;
        if ($left) {
            $p1 = $x;
            $p2 = $x + $size;
        } else {
            $p1 = $x;
            $p2 = $x - $size;
        }
        $points = $p1.','.$y.' '.$p2.','.($y - $half).' '.$p2.','.($y + $half);
        return '<polygon points="'.$points.'" fill="#111827" />';
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

    /**
     * エラーpathを見て「どの要素を赤くするか」を決める。
     */
    private function collectErrorTargets(array $errors): array
    {
        $t = [
            'fiberIdx'  => [],
            'fibersAll' => false,
            'tubeIdx'   => [],
            'tubesAll'  => false,
            'mfd'       => false,
            'connLeft'  => false,
            'connRight' => false,
            'sleeve'    => false,
        ];

        foreach ($errors as $e) {
            $path = (string)($e['path'] ?? '');

            if ($path === 'fibers') $t['fibersAll'] = true;
            if ($path === 'tubes')  $t['tubesAll']  = true;

            if (preg_match('/^fibers\.(\d+)\b/', $path, $m)) {
                $t['fiberIdx'][] = (int)$m[1];
            }
            if (preg_match('/^tubes\.(\d+)\b/', $path, $m)) {
                $t['tubeIdx'][] = (int)$m[1];
            }

            if ($path === 'mfdCount') $t['mfd'] = true;

            if (str_starts_with($path, 'connectors.left'))  $t['connLeft'] = true;
            if (str_starts_with($path, 'connectors.right')) $t['connRight'] = true;

            if (str_starts_with($path, 'sleeveSkuCode')) $t['sleeve'] = true;
        }

        // 重複除去
        $t['fiberIdx'] = array_values(array_unique($t['fiberIdx']));
        $t['tubeIdx']  = array_values(array_unique($t['tubeIdx']));
        return $t;
    }

    private function buildSkuNameMap(): array
    {
        try {
            return DB::table('skus')->pluck('name', 'sku_code')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function buildSkuSvgMap(): array
    {
        $dir = public_path('sku-svg');
        if (!is_dir($dir)) return [];

        $map = [];
        $files = glob($dir . '/*.svg') ?: [];
        foreach ($files as $path) {
            $code = basename($path, '.svg');
            if ($code === '') continue;
            $map[$code] = '/sku-svg/' . $code . '.svg';
        }
        return $map;
    }
}
