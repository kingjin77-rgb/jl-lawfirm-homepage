<?php
/** 직원 로그아웃 — 직원 세션 키만 지운다 (같은 브라우저의 손님 세션 유지). */

$s = staff_current();
if ($s !== null) {
    staff_audit((int)$s['id'], 'staff.logout', 'staff:' . $s['id']);
}
unset($_SESSION['staff']);
session_regenerate_id(true);
redirect('/admin/login');
