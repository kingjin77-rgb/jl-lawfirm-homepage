<?php
/**
 * 등기진행 등록 (엑셀 업로드) — 기존 registration_write 재현.
 * 1) 아파트 선택 + .xlsx 업로드 → 파싱 미리보기(앞 5행 + 건수 + 행별 오류)
 * 2) 확정 → 트랜잭션으로 (단지,동,호) 업서트. 오류가 하나라도 있으면 반입 불가.
 * 업로드 검사(확장자·MIME·ZIP 시그니처·xlsx 구조·10MB)는 app/upload.php.
 * 원본은 웹루트 밖 임시폴더에서 파싱 직후 지우고, 확정용으로는 암호화한 파싱 결과만 1시간 둔다.
 */

require_once __DIR__ . '/../../upload.php';

$staff = staff_require();

$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');
$error = '';
$preview = null;      // ['complex_id'=>, 'complex_name'=>, 'rows'=>, 'errors'=>, 'token'=>]
$result = null;       // ['created'=>, 'updated'=>, 'total'=>]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');
    upload_gc();

    if ($act === 'parse') {
        $complexId = (int)($_POST['complex_id'] ?? 0);
        $file = $_FILES['xlsx'] ?? null;
        if ($complexId <= 0 || db_row('SELECT id FROM complex WHERE id = ?', [$complexId]) === null) {
            $error = '아파트를 선택해 주십시오.';
        } elseif ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = (int)($file['error'] ?? -1);
            $error = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? '파일이 ' . (UPLOAD_MAX_BYTES >> 20) . 'MB 를 넘습니다.'
                : '엑셀 파일을 올려 주십시오. (업로드 오류 코드 ' . $code . ')';
        } else {
            $check = upload_validate_xlsx($file);
            if (!$check['ok']) {
                $error = $check['error'];
                staff_audit((int)$staff['id'], 'import.reject', 'complex:' . $complexId,
                    '업로드 거부: ' . $check['error'] . ' (' . mb_substr((string)$file['name'], 0, 80) . ')');
            } else {
                // 웹루트 밖 임시폴더에 무작위 이름으로 옮겨 파싱하고, 끝나면 무조건 지운다.
                $tmp = upload_tmp_dir() . DIRECTORY_SEPARATOR . 'up_' . bin2hex(random_bytes(16)) . '.tmp';
                if (!move_uploaded_file($file['tmp_name'], $tmp)) {
                    $error = '업로드 파일을 저장하지 못했습니다.';
                } else {
                    try {
                        $parsed = import_parse_xlsx($tmp);
                    } finally {
                        @unlink($tmp);
                    }
                    if (!$parsed['ok']) {
                        $error = $parsed['error'];
                    } else {
                        $token = bin2hex(random_bytes(16));
                        // 원본은 이미 지웠다. 확정 단계용으로 파싱 결과만 암호화해 잠시 둔다.
                        file_put_contents(upload_parsed_path($token),
                            encrypt_field(json_encode(['rows' => $parsed['rows'], 'errors' => $parsed['errors']],
                                JSON_UNESCAPED_UNICODE)), LOCK_EX);
                        $_SESSION['import'] = ['token' => $token, 'complex_id' => $complexId,
                            'name' => mb_substr((string)$file['name'], 0, 120)];
                        $cx = db_row('SELECT name FROM complex WHERE id = ?', [$complexId]);
                        $preview = [
                            'complex_id'   => $complexId,
                            'complex_name' => $cx['name'] ?? '',
                            'file_name'    => (string)$file['name'],
                            'rows'         => $parsed['rows'],
                            'errors'       => $parsed['errors'],
                            'token'        => $token,
                        ];
                    }
                }
            }
        }
    } elseif ($act === 'confirm') {
        $sess = $_SESSION['import'] ?? null;
        $token = (string)($_POST['token'] ?? '');
        if ($sess === null || $token === '' || !hash_equals($sess['token'], $token)) {
            $error = '반입 세션이 만료됐습니다. 파일을 다시 올려 주십시오.';
        } else {
            $path = upload_parsed_path($token);
            $json = is_file($path) ? decrypt_field((string)file_get_contents($path)) : null;
            $parsed = $json !== null ? json_decode($json, true) : null;
            if (!is_array($parsed)) {
                $error = '업로드 자료를 찾지 못했습니다(1시간이 지나면 지워집니다). 다시 올려 주십시오.';
            } elseif ($parsed['errors'] !== []) {
                $error = '오류 행이 있어 반입하지 않았습니다. 엑셀을 고쳐 다시 올려 주십시오.';
            } else {
                try {
                    $r = import_apply((int)$sess['complex_id'], $parsed['rows']);
                    $result = $r + ['total' => count($parsed['rows'])];
                    staff_audit((int)$staff['id'], 'import.xlsx', 'complex:' . (int)$sess['complex_id'],
                        "「{$sess['name']}」 {$result['total']}행 (신규 {$r['created']} · 갱신 {$r['updated']})");
                    @unlink($path);
                    unset($_SESSION['import']);
                } catch (Throwable $e) {
                    error_log('[jlportal] import failed: ' . $e->getMessage());
                    $error = '반입 중 오류가 나 전부 되돌렸습니다. 엑셀 내용을 확인해 주십시오.';
                }
            }
        }
    }
}

render_admin('reg_upload', [
    'pageTitle' => '등기진행 등록 (엑셀)',
    'staff'     => $staff,
    'complexes' => $complexes,
    'error'     => $error,
    'preview'   => $preview,
    'result'    => $result,
]);
