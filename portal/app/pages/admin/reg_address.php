<?php
/**
 * 권리증수령주소 현황 — 손님 등록분(세대별 최신)을 복호화해 보여준다.
 * 직원 전용 화면. 복호화 전문이 나가는 만큼 열람도 staff_audit 에 남긴다.
 */

$staff = staff_require();

$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');
$f = reg_filters_from_get();
[$whereSql, $params] = reg_filters_where($f);

$perPage = 30;
$base = "FROM address_request a
         JOIN household h ON h.id = a.household_id
         JOIN complex c ON c.id = h.complex_id
         LEFT JOIN progress p ON p.household_id = h.id
        WHERE a.id = (SELECT MAX(a2.id) FROM address_request a2 WHERE a2.household_id = h.id)
          AND $whereSql";
$total = (int)(db_row("SELECT COUNT(*) AS n $base", $params)['n'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $pages));
$offset = ($page - 1) * $perPage;
$rows = db_all(
    "SELECT a.id, a.zip, a.addr1_enc, a.addr2_enc, a.created_at, h.id AS hid, h.dong, h.ho,
            c.name AS complex_name,
            (SELECT o.name FROM owner o WHERE o.household_id = h.id ORDER BY o.is_primary DESC, o.id LIMIT 1) AS owner_name
       $base ORDER BY a.id DESC LIMIT $perPage OFFSET $offset", $params);
foreach ($rows as &$r) {
    $r['addr1'] = decrypt_field($r['addr1_enc']) ?? '(복호화 실패)';
    $r['addr2'] = decrypt_field($r['addr2_enc']) ?? '';
}
unset($r);

staff_audit((int)$staff['id'], 'address.list.view', 'page:' . $page, '주소 현황 열람 ' . count($rows) . '건');

render_admin('reg_address', [
    'pageTitle' => '권리증수령주소 현황',
    'staff'     => $staff,
    'complexes' => $complexes,
    'f'         => $f,
    'rows'      => $rows,
    'total'     => $total,
    'page'      => $page,
    'pages'     => $pages,
]);
