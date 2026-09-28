<?php
/** 손님 로그인 — 아파트 선택 + 동 + 호 + 이름 + 생년월일 6자리 */

if (customer_current() !== null) {
    redirect('/home');
}

$error = '';
$lockout = false;
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
        redirect('/home');
    }
    if ($result['error'] === 'lockout') {
        $lockout = true;
    } else {
        $error = $result['error'];
    }
}

// 노출 중인 단지만 선택지로 보여준다.
$complexes = db_all('SELECT id, name FROM complex WHERE exposed = 1 ORDER BY sort, name');

render('login', [
    'pageTitle' => '로그인',
    'complexes' => $complexes,
    'error'     => $error,
    'lockout'   => $lockout,
    'old'       => $old,
]);
