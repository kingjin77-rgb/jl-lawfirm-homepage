<?php
/**
 * 업로드 웹쉘 차단 — 엑셀(.xlsx) 반입 전용 검사기.
 *  1) 확장자 .xlsx 만 (이름 중간의 .php 등 실행 확장자도 거부)   2) 크기 10MB 이하
 *  3) finfo MIME 이 xlsx/zip 계열 (브라우저가 보낸 type 은 믿지 않는다)
 *  4) 파일 앞 4바이트 ZIP 시그니처(PK\x03\x04)
 *  5) ZIP 안에 [Content_Types].xml + xl/workbook.xml
 *  6) 압축 해제 합계 100MB 이하(zip 폭탄) · 실행 확장자/경로탈출 항목 없음
 * 저장은 웹루트(public/) 밖 임시폴더에 무작위 이름으로만 하고, 파싱이 끝나면 즉시 지운다.
 */

const UPLOAD_MAX_BYTES    = 10 * 1024 * 1024;
const UPLOAD_MAX_UNZIPPED = 100 * 1024 * 1024;
const UPLOAD_PARSED_TTL   = 3600;
const UPLOAD_ALLOWED_MIME = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
    'application/x-zip-compressed',
];

/** 웹루트 밖 임시폴더 — config 'tmp_dir' 우선. public/ 안이면 사용 거부. */
function upload_tmp_dir(): string
{
    $dir = (string)(portal_config()['tmp_dir'] ?? '');
    if ($dir === '') {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jlportal_tmp';
    }
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('임시폴더를 만들 수 없습니다.');
    }
    $real = realpath($dir);
    $pub = realpath(__DIR__ . '/../public');
    if ($real === false || ($pub !== false && str_starts_with(
            strtolower($real) . DIRECTORY_SEPARATOR, strtolower($pub) . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('임시폴더가 웹루트 안에 있습니다. config tmp_dir 을 웹루트 밖으로 두십시오.');
    }
    return $real;
}

function upload_parsed_path(string $token): string
{
    return upload_tmp_dir() . DIRECTORY_SEPARATOR . 'jlimport_' . preg_replace('/[^a-f0-9]/', '', $token) . '.bin';
}

/** 1시간 지난 반입 대기 파일·남은 업로드 조각 청소 */
function upload_gc(): void
{
    $dir = upload_tmp_dir();
    foreach (array_merge(glob($dir . DIRECTORY_SEPARATOR . 'jlimport_*.bin') ?: [],
                         glob($dir . DIRECTORY_SEPARATOR . 'up_*.tmp') ?: []) as $f) {
        if (is_file($f) && filemtime($f) < time() - UPLOAD_PARSED_TTL) {
            @unlink($f);
        }
    }
}

/** @return array{ok:bool, error:string} */
function upload_validate_xlsx(array $file): array
{
    $fail = fn(string $m) => ['ok' => false, 'error' => $m];
    $name = (string)($file['name'] ?? '');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('업로드 파일이 아닙니다.');
    }
    if (!preg_match('/\.xlsx$/i', $name)) {
        return $fail('엑셀(.xlsx) 파일만 올릴 수 있습니다.');
    }
    if (preg_match('/\.(php\d?|phtml|phar|pht|inc|cgi|pl|py|sh|asp|aspx|jsp|exe|htaccess)(\.|$)/i', $name)) {
        return $fail('파일 이름에 실행 파일 확장자가 들어 있어 거부했습니다.');
    }
    $size = (int)filesize($tmp);
    if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
        return $fail('파일 크기는 ' . (UPLOAD_MAX_BYTES >> 20) . 'MB 이하여야 합니다.');
    }
    if (!class_exists('finfo')) {
        return $fail('서버에 fileinfo 확장이 없어 파일 형식을 확인할 수 없습니다.');
    }
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
        return $fail('엑셀 파일 형식이 아닙니다 (' . $mime . ').');
    }
    $fh = fopen($tmp, 'rb');
    $sig = $fh ? (string)fread($fh, 4) : '';
    if ($fh) {
        fclose($fh);
    }
    if ($sig !== "PK\x03\x04") {
        return $fail('엑셀(.xlsx) 파일이 아니거나 손상됐습니다.');
    }
    if (!class_exists('ZipArchive')) {
        return $fail('서버에 ZipArchive 확장이 없습니다.');
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        return $fail('엑셀(.xlsx) 파일이 아니거나 손상됐습니다.');
    }
    $total = 0;
    $hasCt = false;
    $hasWb = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        if ($st === false) {
            continue;
        }
        $entry = (string)$st['name'];
        $total += (int)$st['size'];
        // 압축률이 비정상(200배 초과)인 항목은 zip 폭탄으로 본다 — 선언 크기만 믿지 않는다.
        if ((int)$st['comp_size'] > 0 && (int)$st['size'] / (int)$st['comp_size'] > 200 && (int)$st['size'] > 1024 * 1024) {
            $zip->close();
            return $fail('압축률이 비정상인 항목이 있어 거부했습니다.');
        }
        $hasCt = $hasCt || $entry === '[Content_Types].xml';
        $hasWb = $hasWb || $entry === 'xl/workbook.xml';
        if (preg_match('/\.(php\d?|phtml|phar|htaccess|sh|exe)$/i', $entry) || str_contains($entry, '..')) {
            $zip->close();
            return $fail('엑셀 안에 허용되지 않는 항목이 있어 거부했습니다.');
        }
    }
    $zip->close();
    if (!$hasCt || !$hasWb) {
        return $fail('엑셀(.xlsx) 구조가 아닙니다.');
    }
    if ($total > UPLOAD_MAX_UNZIPPED) {
        return $fail('압축을 푼 크기가 너무 큽니다.');
    }
    return ['ok' => true, 'error' => ''];
}
