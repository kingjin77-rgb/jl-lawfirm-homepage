<?php
/**
 * 손님 재인증 — 환불계좌·권리증 주소를 등록/변경하기 전, 최근 5분 내 문자 인증이
 * 없으면 여기서 인증번호를 다시 받는다. (/verify?next=/refund)
 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

$allowed = ['/refund', '/address'];
$next = (string)($_GET['next'] ?? ($_POST['next'] ?? '/home'));
$next = in_array($next, $allowed, true) ? $next : '/home';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'send') {
        customer_send_code('reauth');
        redirect('/verify?next=' . rawurlencode($next));
    }
    if ($act === 'verify') {
        $res = customer_verify_code('reauth', (string)($_POST['code'] ?? ''));
        if ($res['ok']) {
            redirect($next);
        }
        $error = $res['error'];
        if (in_array($res['state'], ['revoked', 'expired'], true)) {
            unset($_SESSION['otp_notice_reauth']['dev_code']);
        }
    }
}

$notice = $_SESSION['otp_notice_reauth'] ?? [];
if (($notice['error'] ?? '') === 'nophone') {
    $error = '등록된 휴대폰이 없어 인증할 수 없습니다. 등기센터 1899-4252 로 연락해 주십시오.';
}

render('otp', [
    'pageTitle' => '본인 확인',
    'customer'  => $customer,
    'hh'        => $hh,
    'mode'      => 'reauth',
    'notice'    => $notice,
    'error'     => $error,
    'action'    => '/verify',
    'next'      => $next,
]);
