<?php
/**
 * 직원 로그인 — 아이디/비밀번호. 5회 실패 15분 잠금은 staff_login() 이 처리.
 * 성공 기록은 staff_login() 안에서 staff_audit('staff.login') 으로 남는다.
 */

if (staff_current() !== null) {
    redirect('/admin');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $loginId = trim((string)($_POST['login_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($loginId === '' || $password === '') {
        $error = '아이디와 비밀번호를 모두 넣어 주십시오.';
    } else {
        $res = staff_login($loginId, $password);
        if ($res['ok']) {
            redirect('/admin');
        }
        $error = $res['error'];
    }
}

render_admin('login', [
    'pageTitle' => '직원 로그인',
    'error'     => $error,
]);
