<?php
/** 직원 작업기록 열람 — admin 전용. staff_audit 최신순 30건/쪽. */

$staff = staff_require_admin();

$perPage = 30;
$total = (int)(db_row('SELECT COUNT(*) AS n FROM staff_audit')['n'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $pages));
$offset = ($page - 1) * $perPage;

$rows = db_all(
    "SELECT a.*, s.login_id, s.name AS staff_name
       FROM staff_audit a
       LEFT JOIN staff s ON s.id = a.staff_id
      ORDER BY a.id DESC LIMIT $perPage OFFSET $offset");

render_admin('stats_audit', [
    'pageTitle' => '직원 작업기록',
    'staff'     => $staff,
    'rows'      => $rows,
    'total'     => $total,
    'page'      => $page,
    'pages'     => $pages,
]);
