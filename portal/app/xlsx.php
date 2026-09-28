<?php
/**
 * XLSX 읽기 — 외부 라이브러리 없이 ZipArchive + SimpleXML 로 푼다.
 * (xlsx = zip 안의 XML. 가비아 공유호스팅에 컴포저를 못 쓰므로 직접 판다.)
 *
 * xlsx_read_rows(): 첫 시트를 [행번호 => [열번호 => 값문자열]] 로 돌려준다.
 *  - 공유 문자열(s) / 인라인 문자열(inlineStr) / 수식 결과 / 숫자 모두 문자열로.
 *  - 숫자는 12.0 → "12" 처럼 정수면 소수점을 떼서 돌려준다 (동·호·생년월일 대응).
 *  - 빈 칸은 키 자체가 없다. 열 번호는 A=1.
 */

/** "AB12" → 열번호 (A=1, Z=26, AA=27 …) */
function xlsx_col_number(string $cellRef): int
{
    $col = 0;
    foreach (str_split($cellRef) as $ch) {
        if ($ch >= 'A' && $ch <= 'Z') {
            $col = $col * 26 + (ord($ch) - 64);
        } elseif ($ch >= '0' && $ch <= '9') {
            break;
        }
    }
    return $col;
}

/** 숫자 문자열 정리 — "750509.0" → "750509", 그 외 소수는 그대로 */
function xlsx_number_to_string(string $raw): string
{
    if (!is_numeric($raw)) {
        return $raw;
    }
    $f = (float)$raw;
    if (floor($f) == $f && abs($f) < 1e15) {
        return number_format($f, 0, '.', '');
    }
    return rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
}

/**
 * @return array{ok:bool, error:string, rows:array<int,array<int,string>>}
 */
function xlsx_read_rows(string $path): array
{
    $fail = fn(string $msg) => ['ok' => false, 'error' => $msg, 'rows' => []];

    if (!class_exists('ZipArchive')) {
        return $fail('서버에 ZipArchive 확장이 없습니다.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return $fail('엑셀(.xlsx) 파일이 아니거나 손상됐습니다.');
    }

    // 공유 문자열 사전
    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = @simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string)$si->t;
                } else {
                    // rich text: <r><t>…</t></r> 조각을 이어붙인다
                    $buf = '';
                    foreach ($si->r as $r) {
                        $buf .= (string)$r->t;
                    }
                    $shared[] = $buf;
                }
            }
        }
    }

    // 첫 시트 — workbook 순서상 첫 번째의 관계를 따라간다. 없으면 sheet1.xml.
    $sheetXml = false;
    $wbXml = $zip->getFromName('xl/workbook.xml');
    $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbXml !== false && $relXml !== false) {
        $wb = @simplexml_load_string($wbXml);
        $rels = @simplexml_load_string($relXml);
        if ($wb !== false && $rels !== false && isset($wb->sheets->sheet[0])) {
            $rid = (string)$wb->sheets->sheet[0]->attributes(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            foreach ($rels->Relationship as $rel) {
                if ((string)$rel['Id'] === $rid) {
                    $target = ltrim((string)$rel['Target'], '/');
                    if (!str_starts_with($target, 'xl/')) {
                        $target = 'xl/' . $target;
                    }
                    $sheetXml = $zip->getFromName($target);
                    break;
                }
            }
        }
    }
    if ($sheetXml === false) {
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    }
    $zip->close();
    if ($sheetXml === false) {
        return $fail('엑셀 안에서 시트를 찾지 못했습니다.');
    }
    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false) {
        return $fail('시트 XML 을 읽지 못했습니다.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowNo = (int)$row['r'];
        $cells = [];
        $autoCol = 0;
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $col = $ref !== '' ? xlsx_col_number($ref) : $autoCol + 1;
            $autoCol = $col;
            $type = (string)$c['t'];
            $val = null;
            if ($type === 's') {                        // 공유 문자열
                $idx = (int)$c->v;
                $val = $shared[$idx] ?? '';
            } elseif ($type === 'inlineStr') {          // 인라인 문자열
                $val = isset($c->is->t) ? (string)$c->is->t : '';
                if ($val === '' && isset($c->is->r)) {
                    foreach ($c->is->r as $r) {
                        $val .= (string)$r->t;
                    }
                }
            } elseif ($type === 'str') {                // 수식의 문자열 결과
                $val = (string)$c->v;
            } elseif ($type === 'b') {                  // 불리언
                $val = ((string)$c->v === '1') ? '1' : '0';
            } elseif (isset($c->v)) {                   // 숫자(기본)
                $val = xlsx_number_to_string((string)$c->v);
            }
            if ($val !== null) {
                $cells[$col] = $val;
            }
        }
        if ($cells !== []) {
            $rows[$rowNo] = $cells;
        }
    }
    return ['ok' => true, 'error' => '', 'rows' => $rows];
}

/** 엑셀 날짜 직렬값(1900 기준) → 'Y-m-d'. 날짜로 볼 수 없으면 null. */
function xlsx_serial_to_date(float $serial): ?string
{
    if ($serial < 20000 || $serial > 80000) {          // 1954~2119년 범위만 인정
        return null;
    }
    $unix = (int)round(($serial - 25569) * 86400);     // 25569 = 1970-01-01
    return gmdate('Y-m-d', $unix);
}
