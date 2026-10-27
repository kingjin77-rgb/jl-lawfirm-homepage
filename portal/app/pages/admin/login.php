<?php
/**
 * 직원 로그인 1단계 — 아이디/비밀번호. 5회 실패 15분 잠금 + IP 차단은 staff_login() 이 처리.
 * 통과하면 OTP 화면(/admin/otp), 처음이면 OTP 등록 화면(/admin/otp/setup)으로 간다.
 */

if (staff_current() !== null) {
    redirect('/admin');
}

$error = '';
$expired = session_take_expired('staff');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $loginId = trim((string)($_POST['login_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($loginId === '' || $password === '') {
        $error = '아이디와 비밀번호를 모두 넣어 주십시오.';
    } else {
        $res = staff_login($loginId, $password);
        if ($res['ok']) {
            redirect($res['next']);
        }
        $error = $res['error'];
    }
}

render_admin('login', [
    'pageTitle' => '직원 로그인',
    'error'     => $error,
    'expired'   => $expired,
]);
