<?php
/** SMS 발송 내역 — sms_log 최신순. */

$staff = staff_require();

$perPage = 30;
$total = (int)(db_row('SELECT COUNT(*) AS n FROM sms_log')['n'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $pages));
$offset = ($page - 1) * $perPage;

$rows = db_all(
    "SELECT m.*, c.name AS complex_name, s.name AS staff_name
       FROM sms_log m
       LEFT JOIN complex c ON c.id = m.complex_id
       LEFT JOIN staff s ON s.id = m.staff_id
      ORDER BY m.id DESC LIMIT $perPage OFFSET $offset");

render_admin('member_sms_log', [
    'pageTitle' => 'SMS 발송 내역',
    'staff'     => $staff,
    'rows'      => $rows,
    'total'     => $total,
    'page'      => $page,
    'pages'     => $pages,
]);
