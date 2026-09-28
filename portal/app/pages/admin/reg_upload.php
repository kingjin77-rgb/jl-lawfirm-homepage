<?php
/**
 * 등기진행 등록 (엑셀 업로드) — 기존 registration_write 재현.
 * 1) 아파트 선택 + .xlsx 업로드 → 파싱 미리보기(앞 5행 + 건수 + 행별 오류)
 * 2) 확정 → 트랜잭션으로 (단지,동,호) 업서트. 오류가 하나라도 있으면 반입 불가.
 * 업로드 파일은 웹루트 밖 임시폴더에 세션 전용 이름으로 두고 확정 때 다시 판다.
 */

$staff = staff_require();

$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');
$error = '';
$preview = null;      // ['complex_id'=>, 'complex_name'=>, 'rows'=>, 'errors'=>, 'token'=>]
$result = null;       // ['created'=>, 'updated'=>, 'total'=>]

/** 세션 전용 임시 파일 경로 (웹루트 밖) */
function upload_tmp_path(string $token): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jlimport_' . preg_replace('/[^a-f0-9]/', '', $token) . '.xlsx';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'parse') {
        $complexId = (int)($_POST['complex_id'] ?? 0);
        $file = $_FILES['xlsx'] ?? null;
        if ($complexId <= 0 || db_row('SELECT id FROM complex WHERE id = ?', [$complexId]) === null) {
            $error = '아파트를 선택해 주십시오.';
        } elseif ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = '엑셀 파일을 올려 주십시오. (업로드 오류 코드 ' . (int)($file['error'] ?? -1) . ')';
        } elseif ((int)$file['size'] > 20 * 1024 * 1024) {
            $error = '파일이 20MB 를 넘습니다.';
        } else {
            $token = bin2hex(random_bytes(16));
            $tmp = upload_tmp_path($token);
            if (!move_uploaded_file($file['tmp_name'], $tmp)) {
                $error = '업로드 파일을 저장하지 못했습니다.';
            } else {
                $parsed = import_parse_xlsx($tmp);
                if (!$parsed['ok']) {
                    @unlink($tmp);
                    $error = $parsed['error'];
                } else {
                    $_SESSION['import'] = ['token' => $token, 'complex_id' => $complexId, 'name' => (string)$file['name']];
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
    } elseif ($act === 'confirm') {
        $sess = $_SESSION['import'] ?? null;
        $token = (string)($_POST['token'] ?? '');
        if ($sess === null || $token === '' || !hash_equals($sess['token'], $token)) {
            $error = '반입 세션이 만료됐습니다. 파일을 다시 올려 주십시오.';
        } else {
            $tmp = upload_tmp_path($token);
            if (!is_file($tmp)) {
                $error = '업로드 파일을 찾지 못했습니다. 다시 올려 주십시오.';
            } else {
                $parsed = import_parse_xlsx($tmp);
                if (!$parsed['ok']) {
                    $error = $parsed['error'];
                } elseif ($parsed['errors'] !== []) {
                    $error = '오류 행이 있어 반입하지 않았습니다. 엑셀을 고쳐 다시 올려 주십시오.';
                    $cx = db_row('SELECT name FROM complex WHERE id = ?', [(int)$sess['complex_id']]);
                    $preview = [
                        'complex_id'   => (int)$sess['complex_id'],
                        'complex_name' => $cx['name'] ?? '',
                        'file_name'    => (string)$sess['name'],
                        'rows'         => $parsed['rows'],
                        'errors'       => $parsed['errors'],
                        'token'        => $token,
                    ];
                } else {
                    try {
                        $r = import_apply((int)$sess['complex_id'], $parsed['rows']);
                        $result = $r + ['total' => count($parsed['rows'])];
                        staff_audit((int)$staff['id'], 'import.xlsx', 'complex:' . (int)$sess['complex_id'],
                            "「{$sess['name']}」 {$result['total']}행 (신규 {$r['created']} · 갱신 {$r['updated']})");
                        @unlink($tmp);
                        unset($_SESSION['import']);
                    } catch (Throwable $e) {
                        $error = '반입 중 오류가 나 전부 되돌렸습니다: ' . $e->getMessage();
                    }
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
