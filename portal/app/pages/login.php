<?php
/**
 * 손님 로그인 1단계 — 아파트 선택 + 동 + 호 + 이름 + 생년월일 6자리.
 * 통과하면 등록 휴대폰으로 인증번호를 보내고 /login/verify(2단계)로 간다.
 * 휴대폰이 없는 세대는 등기센터 연락 안내 카드로 막는다.
 */

if (customer_current() !== null) {
    redirect('/home');
}
if (isset($_GET['reset'])) {
    unset($_SESSION['customer_pending'], $_SESSION['otp_notice_login']);
}

$error = '';
$lockout = false;
$ipblock = false;
$nophone = false;
$expired = session_take_expired('customer');
$old = ['complex_id' => 0, 'dong' => '', 'ho' => '', 'name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $old['complex_id'] = (int)($_POST['complex_id'] ?? 0);
    $old['dong'] = trim((string)($_POST['dong'] ?? ''));
    $old['ho']   = trim((string)($_POST['ho'] ?? ''));
    $old['name'] = trim((string)($_POST['name'] ?? ''));
    $birth6 = (string)($_POST['birth6'] ?? '');

    $result = customer_login($old['complex_id'], $old['dong'], $old['ho'], $old['name'], $birth6);
    if ($result['ok']) {
        redirect('/login/verify');
    }
    match ($result['error']) {
        'lockout' => $lockout = true,
        'ipblock' => $ipblock = true,
        'nophone' => $nophone = true,
        default   => $error = $result['error'],
    };
}

// 노출 중인 단지만 선택지로 보여준다.
$complexes = db_all('SELECT id, name FROM complex WHERE exposed = 1 ORDER BY sort, name');

render('login', [
    'pageTitle' => '로그인',
    'complexes' => $complexes,
    'error'     => $error,
    'lockout'   => $lockout,
    'ipblock'   => $ipblock,
    'nophone'   => $nophone,
    'expired'   => $expired,
    'old'       => $old,
]);
