<?php
/**
 * 복구코드 — OTP 등록 직후 한 번 보여주고, 본인이 새로 발급(OTP 재확인 필요)할 수 있다.
 * 원문은 화면에만 나가고 DB 에는 HMAC 해시만 남는다.
 */

$staff = staff_require();
$codes = $_SESSION['recovery_show'] ?? null;
unset($_SESSION['recovery_show']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    staff_require_reauth('/admin/otp/recovery');
    $codes = recovery_codes_issue((int)$staff['id']);
    staff_audit((int)$staff['id'], 'staff.recovery.reissue', 'staff:' . $staff['id'], '복구코드 8개 새로 발급');
}

render_admin('otp_recovery', [
    'pageTitle' => '복구코드',
    'staff'     => $staff,
    'codes'     => $codes,
    'left'      => recovery_codes_left((int)$staff['id']),
]);
