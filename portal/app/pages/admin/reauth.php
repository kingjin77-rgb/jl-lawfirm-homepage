<?php
/**
 * 직원 민감 작업 재확인 — CSV 내보내기 · 복호화 목록 열람(보기) · 직원계정 관리 · 보안 설정 앞.
 * 최근 10분 안에 OTP 를 넣지 않았으면 여기서 다시 넣는다. 복구코드는 받지 않는다.
 */

$staff = staff_require();
$next = safe_next((string)($_GET['next'] ?? ($_POST['next'] ?? '/admin')), '/admin', '/admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $res = staff_otp_check((int)$staff['id'], (string)($_POST['code'] ?? ''), false);
    if ($res['ok']) {
        session_regenerate_id(true);
        $_SESSION['staff']['otp_at'] = time();
        staff_audit((int)$staff['id'], 'staff.reauth', 'staff:' . $staff['id'], '민감 작업 재확인 → ' . mb_substr($next, 0, 120));
        redirect($next);
    }
    $error = $res['error'];
}

render_admin('otp', [
    'pageTitle' => 'OTP 재확인',
    'staff'     => $staff,
    'mode'      => 'reauth',
    'who'       => $staff,
    'error'     => $error,
    'action'    => '/admin/reauth',
    'next'      => $next,
]);
