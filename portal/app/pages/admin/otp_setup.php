<?php
/**
 * 직원 OTP 첫 등록 — 비밀번호를 통과했지만 OTP 가 없는 직원.
 *  ① admin 이 발급한 등록코드(1회용·24시간)를 넣어야 비밀키가 열린다 — 비밀번호만 훔친 사람은 등록 불가.
 *  ② 비밀키(base32) + otpauth URI + QR(서버 SVG) 을 보여주고, 앱에 나온 코드로 확인해야 켜진다.
 *  ③ 켜지는 순간 복구코드 8개를 한 번만 보여준다.
 * 등록코드·확인코드 실패는 OTP 와 같은 계정 잠금(5회 → 15분)·IP 원장에 들어간다.
 */

require_once __DIR__ . '/../../qr.php';

$pending = staff_pending();
if ($pending === null) {
    redirect('/admin/login');
}
$sid = (int)$pending['id'];
if (staff_totp_enabled($sid)) {
    redirect('/admin/otp');
}

// 실패 카운터를 붙일 자리 (비밀키 없는 미확정 행)
db_exec("INSERT IGNORE INTO staff_totp (staff_id, secret_enc, enabled, last_step) VALUES (?, '', 0, 0)", [$sid]);
$setup = $_SESSION['staff_setup'] ?? null;
if ($setup !== null && (int)$setup['id'] !== $sid) {
    $setup = null;
}
$locked = (int)(db_row('SELECT fail_until > NOW() AS l FROM staff_totp WHERE staff_id = ?', [$sid])['l'] ?? 0) === 1;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');
    if ($locked) {
        $error = '코드를 ' . STAFF_OTP_MAX_FAILS . '회 틀려 ' . STAFF_OTP_LOCK_MINUTES . '분 동안 잠갔습니다.';
    } elseif (ip_blocked_for_staff(client_ip()) !== null) {
        $error = IPBLOCK_MESSAGE;
    } elseif ($act === 'enroll') {
        if (staff_enroll_check($sid, (string)($_POST['enroll_code'] ?? ''))) {
            staff_totp_begin($sid);                      // 등록코드를 통과한 뒤에야 비밀키를 만든다
            $_SESSION['staff_setup'] = ['id' => $sid, 'at' => time(), 'enroll_ok' => true];
            staff_audit($sid, 'staff.otp.enroll_code', 'staff:' . $sid, '등록코드 확인');
            redirect('/admin/otp/setup');
        }
        staff_otp_fail($sid);
        $error = '등록코드가 맞지 않거나 기한(24시간)이 지났습니다. admin 직원에게 새 코드를 받아 주십시오.';
    } elseif ($act === 'confirm') {
        if (empty($setup['enroll_ok'])) {
            $error = '등록 화면이 만료됐습니다. 등록코드부터 다시 넣어 주십시오.';
        } elseif (staff_totp_verify($sid, (string)($_POST['code'] ?? ''), false)) {
            staff_totp_enable($sid);
            staff_enroll_clear($sid);                    // 등록코드는 1회용
            db_exec('UPDATE staff_totp SET fail_count = 0, fail_until = NULL WHERE staff_id = ?', [$sid]);
            $codes = recovery_codes_issue($sid);
            staff_audit($sid, 'staff.otp.enroll', 'staff:' . $sid, 'OTP 등록 · 복구코드 8개 발급');
            staff_complete_login($pending, 'OTP 첫 등록 후 로그인');
            $_SESSION['recovery_show'] = $codes;
            redirect('/admin/otp/recovery');
        } else {
            staff_otp_fail($sid);
            $error = '코드가 맞지 않습니다. 앱에 지금 나온 6자리를 넣어 주십시오. (휴대폰 시각이 자동 설정인지도 확인)';
        }
    }
    $locked = (int)(db_row('SELECT fail_until > NOW() AS l FROM staff_totp WHERE staff_id = ?', [$sid])['l'] ?? 0) === 1;
}

$state = 'secret';
if ($locked) {
    $state = 'locked';
} elseif (empty($setup['enroll_ok'])) {
    $state = staff_enroll_valid($sid) ? 'enroll' : 'nocode';
}

$data = [
    'pageTitle' => 'OTP 등록',
    'who'       => $pending,
    'state'     => $state,
    'error'     => $error,
];
if ($state === 'secret') {
    $row = staff_totp_row($sid);
    $secret = $row !== null ? staff_totp_secret($row) : null;
    if ($secret === null) {
        unset($_SESSION['staff_setup']);
        redirect('/admin/otp/setup');
    }
    $b32 = base32_encode($secret);
    $uri = totp_uri($b32, (string)$pending['login_id']);
    $data += [
        'secret' => trim(chunk_split($b32, 4, ' ')),
        'uri'    => $uri,
        'qrSvg'  => QrEncoder::svg($uri, 5),
    ];
}
render_admin('otp_setup', $data);
