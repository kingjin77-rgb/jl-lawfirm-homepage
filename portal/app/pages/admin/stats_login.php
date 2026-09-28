<?php
/** 접속 통계 — 일자별 손님 로그인 수 (최근 30일, 성공/실패). */

$staff = staff_require();

$rows = db_all(
    "SELECT DATE(created_at) AS d,
            SUM(ok = 1) AS ok_cnt,
            SUM(ok = 0) AS fail_cnt
       FROM login_log
      WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY DATE(created_at)
      ORDER BY d DESC");

$max = 1;
foreach ($rows as $r) {
    $max = max($max, (int)$r['ok_cnt']);
}

render_admin('stats_login', [
    'pageTitle' => '일자별 손님 로그인',
    'staff'     => $staff,
    'rows'      => $rows,
    'max'       => $max,
]);
