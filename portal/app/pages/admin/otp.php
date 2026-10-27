<?php
/**
 * 직원 로그인 2단계 — TOTP 6자리 또는 복구코드(1회용).
 * 같은 코드 재사용 금지 · 실패는 IP 차단 원장에 남는다.
 */

$pending = staff_pending();
if ($pending === null) {
    redirect('/admin/login');
}
if (!staff_totp_enabled((int)$pending['id'])) {
    redirect('/admin/otp/setup');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $res = staff_otp_check((int)$pending['id'], (string)($_POST['code'] ?? ''), true);
    if ($res['ok']) {
        staff_complete_login($pending, $res['via'] === 'recovery' ? '복구코드로 로그인' : 'OTP 로그인');
        if ($res['via'] === 'recovery' && recovery_codes_left((int)$pending['id']) <= 2) {
            $_SESSION['flash_recovery_low'] = 1;
        }
        redirect('/admin');
    }
    $error = $res['error'];
}

render_admin('otp', [
    'pageTitle' => '2단계 인증',
    'mode'      => 'login',
    'who'       => $pending,
    'error'     => $error,
    'action'    => '/admin/otp',
    'next'      => '',
]);
