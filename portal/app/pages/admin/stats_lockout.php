<?php
/**
 * 조회 실패·잠금 — 최근 1시간 실패가 몰린 세대(잠금 후보/잠금 중)와
 * 최근 실패 기록. 손님 잠금은 1시간 10회 실패 기준(auth.php)이다.
 */

$staff = staff_require();

$hot = db_all(
    "SELECT l.complex_id, c.name AS complex_name, l.dong, l.ho, COUNT(*) AS fails,
            MAX(l.created_at) AS last_at
       FROM login_log l
       LEFT JOIN complex c ON c.id = l.complex_id
      WHERE l.ok = 0 AND l.created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)
      GROUP BY l.complex_id, l.dong, l.ho
      ORDER BY fails DESC, last_at DESC
      LIMIT 50");

$recent = db_all(
    "SELECT l.created_at, c.name AS complex_name, l.dong, l.ho, l.ip
       FROM login_log l
       LEFT JOIN complex c ON c.id = l.complex_id
      WHERE l.ok = 0
      ORDER BY l.id DESC LIMIT 50");

render_admin('stats_lockout', [
    'pageTitle' => '조회 실패·잠금',
    'staff'     => $staff,
    'hot'       => $hot,
    'recent'    => $recent,
]);
