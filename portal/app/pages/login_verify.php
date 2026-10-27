<?php
/**
 * 손님 로그인 2단계 — 등록 휴대폰으로 보낸 6자리 인증번호 확인.
 * 3분 유효 · 5회 오입력 폐기 · 재발송 60초/시간당 5회 (sms.php)
 */

if (customer_current() !== null) {
    redirect('/home');
}
if (!isset($_SESSION['customer_pending'])) {
    redirect('/login');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'send') {
        $r = customer_send_code('login');
        if (($r['error'] ?? '') === 'nophone') {
            unset($_SESSION['customer_pending']);
            redirect('/login');
        }
        redirect('/login/verify');                      // 새로고침 재전송 방지 (PRG)
    }
    if ($act === 'verify') {
        $res = customer_verify_code('login', (string)($_POST['code'] ?? ''));
        if ($res['ok']) {
            redirect('/home');
        }
        $error = $res['error'];
        if (in_array($res['state'], ['revoked', 'expired'], true)) {
            unset($_SESSION['otp_notice_login']['dev_code']);   // 폐기된 번호는 더 보여주지 않는다
        }
    }
}

render('otp', [
    'pageTitle' => '본인 확인 문자',
    'mode'      => 'login',
    'notice'    => $_SESSION['otp_notice_login'] ?? [],
    'error'     => $error,
    'action'    => '/login/verify',
    'next'      => '/home',
]);
