<?php
/**
 * QR 코드 → SVG (직원 OTP 등록 화면 전용). 외부 라이브러리 없이 직접 구현했다.
 * 범위: 바이트 모드 · 오류정정 M · 버전 1~20 · 마스크 8종 중 벌점 최소.
 * (ISO/IEC 18004 절차: 데이터 비트열 → RS 오류정정 → 블록 교차 → 배치 → 마스크 → 형식/버전 정보)
 * 서버에서 SVG 로 그리므로 화면에 스크립트가 필요 없다 (CSP 영향 없음).
 */

final class QrEncoder
{
    // 인덱스 = 버전. 오류정정 M 기준 (ISO 18004 표 9)
    private const ECC_PER_BLOCK_M = [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26];
    private const NUM_BLOCKS_M    = [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16];
    private const MAX_VERSION     = 20;
    private const ECC_FORMAT_M    = 0;

    private int $size = 0;
    /** @var array<int,array<int,bool>> [y][x] */
    private array $mod = [];
    /** @var array<int,array<int,bool>> */
    private array $fn = [];

    public static function svg(string $text, int $px = 4): string
    {
        $q = new self();
        $m = $q->encode($text);
        $n = count($m);
        $border = 4;
        $dim = $n + $border * 2;
        $path = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $path .= 'M' . ($x + $border) . ',' . ($y + $border) . 'h1v1h-1z';
                }
            }
        }
        $w = $dim * $px;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $w
            . '" height="' . $w . '" shape-rendering="crispEdges" role="img" aria-label="OTP 등록 QR 코드">'
            . '<rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }

    /** @return array<int,array<int,bool>> */
    public function encode(string $data): array
    {
        $len = strlen($data);
        $ver = 0;
        for ($v = 1; $v <= self::MAX_VERSION; $v++) {
            $capBits = self::numDataCodewords($v) * 8;
            $need = 4 + ($v < 10 ? 8 : 16) + $len * 8;
            if ($need <= $capBits) {
                $ver = $v;
                break;
            }
        }
        if ($ver === 0) {
            throw new RuntimeException('QR 데이터가 너무 깁니다.');
        }

        // 비트열: 모드(0100) + 글자수 + 데이터 + 종결 + 패딩
        $bits = [];
        $push = function (int $val, int $n) use (&$bits): void {
            for ($i = $n - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };
        $push(0b0100, 4);
        $push($len, $ver < 10 ? 8 : 16);
        for ($i = 0; $i < $len; $i++) {
            $push(ord($data[$i]), 8);
        }
        $cap = self::numDataCodewords($ver) * 8;
        $push(0, min(4, $cap - count($bits)));
        $push(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $cap; $pad ^= 0xEC ^ 0x11) {
            $push($pad, 8);
        }
        $codewords = [];
        foreach (array_chunk($bits, 8) as $byteBits) {
            $b = 0;
            foreach ($byteBits as $bit) {
                $b = ($b << 1) | $bit;
            }
            $codewords[] = $b;
        }

        $this->size = $ver * 4 + 17;
        $this->mod = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->fn = $this->mod;
        $this->drawFunctionPatterns($ver);
        $all = $this->addEccAndInterleave($codewords, $ver);
        $this->drawCodewords($all);

        $best = -1;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $p = $this->penalty();
            if ($p < $bestPenalty) {
                $bestPenalty = $p;
                $best = $mask;
            }
            $this->applyMask($mask);                    // XOR 되돌리기
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);
        return $this->mod;
    }

    private static function numRawDataModules(int $ver): int
    {
        $r = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $na = intdiv($ver, 7) + 2;
            $r -= (25 * $na - 10) * $na - 55;
            if ($ver >= 7) {
                $r -= 36;
            }
        }
        return $r;
    }

    private static function numDataCodewords(int $ver): int
    {
        return intdiv(self::numRawDataModules($ver), 8) - self::ECC_PER_BLOCK_M[$ver] * self::NUM_BLOCKS_M[$ver];
    }

    private function setFn(int $x, int $y, bool $dark): void
    {
        $this->mod[$y][$x] = $dark;
        $this->fn[$y][$x] = true;
    }

    private function drawFunctionPatterns(int $ver): void
    {
        $s = $this->size;
        for ($i = 0; $i < $s; $i++) {
            $this->setFn(6, $i, $i % 2 === 0);
            $this->setFn($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$s - 4, 3], [3, $s - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $s && $y >= 0 && $y < $s) {
                        $d = max(abs($dx), abs($dy));
                        $this->setFn($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        $pos = $this->alignmentPositions($ver);
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setFn($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0);                       // 자리 확보 (나중에 덮어씀)
        if ($ver >= 7) {
            $rem = $ver;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($ver << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $dark = (($bits >> $i) & 1) === 1;
                $a = $s - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->setFn($a, $b, $dark);
                $this->setFn($b, $a, $dark);
            }
        }
    }

    private function alignmentPositions(int $ver): array
    {
        if ($ver === 1) {
            return [];
        }
        $na = intdiv($ver, 7) + 2;
        $step = (int)(ceil(($ver * 4 + 4) / ($na * 2 - 2)) * 2);
        $res = [];
        for ($i = 0, $pos = $this->size - 7; $i < $na - 1; $i++, $pos -= $step) {
            array_unshift($res, $pos);
        }
        array_unshift($res, 6);
        return $res;
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::ECC_FORMAT_M << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $b = fn(int $i): bool => (($bits >> $i) & 1) === 1;
        $s = $this->size;
        for ($i = 0; $i <= 5; $i++) {
            $this->setFn(8, $i, $b($i));
        }
        $this->setFn(8, 7, $b(6));
        $this->setFn(8, 8, $b(7));
        $this->setFn(7, 8, $b(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFn(14 - $i, 8, $b($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFn($s - 1 - $i, 8, $b($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFn(8, $s - 15 + $i, $b($i));
        }
        $this->setFn(8, $s - 8, true);                  // 고정 검은 모듈
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private static function rsDivisor(int $degree): array
    {
        $r = array_fill(0, $degree, 0);
        $r[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $r[$j] = self::gfMul($r[$j], $root);
                if ($j + 1 < $degree) {
                    $r[$j] ^= $r[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $r;
    }

    private static function rsRemainder(array $data, array $div): array
    {
        $r = array_fill(0, count($div), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($r);
            $r[] = 0;
            foreach ($div as $i => $d) {
                $r[$i] ^= self::gfMul($d, $factor);
            }
        }
        return $r;
    }

    private function addEccAndInterleave(array $data, int $ver): array
    {
        $numBlocks = self::NUM_BLOCKS_M[$ver];
        $eccLen = self::ECC_PER_BLOCK_M[$ver];
        $raw = intdiv(self::numRawDataModules($ver), 8);
        $numShort = $numBlocks - $raw % $numBlocks;
        $shortLen = intdiv($raw, $numBlocks);
        $div = self::rsDivisor($eccLen);
        $blocks = [];
        for ($i = 0, $k = 0; $i < $numBlocks; $i++) {
            $n = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat = array_slice($data, $k, $n);
            $k += $n;
            $ecc = self::rsRemainder($dat, $div);
            if ($i < $numShort) {
                $dat[] = 0;
            }
            $blocks[] = array_merge($dat, $ecc);
        }
        $out = [];
        $blen = count($blocks[0]);
        for ($i = 0; $i < $blen; $i++) {
            foreach ($blocks as $j => $blk) {
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $out[] = $blk[$i];
                }
            }
        }
        return $out;
    }

    private function drawCodewords(array $data): void
    {
        $s = $this->size;
        $total = count($data) * 8;
        $i = 0;
        for ($right = $s - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $s; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $s - 1 - $vert : $vert;
                    if (!$this->fn[$y][$x] && $i < $total) {
                        $this->mod[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        $s = $this->size;
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                if ($this->fn[$y][$x]) {
                    continue;
                }
                $inv = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                };
                if ($inv) {
                    $this->mod[$y][$x] = !$this->mod[$y][$x];
                }
            }
        }
    }

    /** 벌점 — 규칙 1(연속 5개+), 2(2x2), 4(명암 비율). 마스크 선택용 근사치. */
    private function penalty(): int
    {
        $s = $this->size;
        $p = 0;
        $dark = 0;
        for ($a = 0; $a < $s; $a++) {
            $runR = 1;
            $runC = 1;
            for ($b = 1; $b < $s; $b++) {
                if ($this->mod[$a][$b] === $this->mod[$a][$b - 1]) {
                    $runR++;
                } else {
                    $p += $runR >= 5 ? $runR - 2 : 0;
                    $runR = 1;
                }
                if ($this->mod[$b][$a] === $this->mod[$b - 1][$a]) {
                    $runC++;
                } else {
                    $p += $runC >= 5 ? $runC - 2 : 0;
                    $runC = 1;
                }
            }
            $p += ($runR >= 5 ? $runR - 2 : 0) + ($runC >= 5 ? $runC - 2 : 0);
        }
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                $c = $this->mod[$y][$x];
                $dark += $c ? 1 : 0;
                if ($x + 1 < $s && $y + 1 < $s && $c === $this->mod[$y][$x + 1]
                    && $c === $this->mod[$y + 1][$x] && $c === $this->mod[$y + 1][$x + 1]) {
                    $p += 3;
                }
            }
        }
        $total = $s * $s;
        $k = (int)ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $p + max(0, $k) * 10;
    }
}
