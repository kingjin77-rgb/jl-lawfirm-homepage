<?php
/**
 * 채권환불 신청현황 — 복호화 목록 + 행별 환불일 바로 입력.
 * 직원 전용. 열람도 작업기록에 남긴다.
 */

$staff = staff_require();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $rid = (int)($_POST['refund_id'] ?? 0);
    $date = trim((string)($_POST['refund_date'] ?? ''));
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = '환불일 형식이 올바르지 않습니다.';
    } elseif ($rid > 0) {
        db_exec('UPDATE refund_request SET refund_date = ? WHERE id = ?', [$date !== '' ? $date : null, $rid]);
        staff_audit((int)$staff['id'], 'refund.date', "refund:$rid", $date !== '' ? "환불일 $date" : '환불일 지움');
        $notice = '환불일을 저장했습니다.';
    }
}

$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');
$f = reg_filters_from_get();
[$whereSql, $params] = reg_filters_where($f);

$perPage = 30;
$base = "FROM refund_request rr
         JOIN household h ON h.id = rr.household_id
         JOIN complex c ON c.id = h.complex_id
         LEFT JOIN progress p ON p.household_id = h.id
        WHERE rr.id = (SELECT MAX(r2.id) FROM refund_request r2 WHERE r2.household_id = h.id)
          AND $whereSql";
$total = (int)(db_row("SELECT COUNT(*) AS n $base", $params)['n'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $pages));
$offset = ($page - 1) * $perPage;
$rows = db_all(
    "SELECT rr.id, rr.bank, rr.account_enc, rr.holder, rr.refund_date, rr.created_at,
            h.id AS hid, h.dong, h.ho, c.name AS complex_name,
            (SELECT o.name FROM owner o WHERE o.household_id = h.id ORDER BY o.is_primary DESC, o.id LIMIT 1) AS owner_name
       $base ORDER BY rr.id DESC LIMIT $perPage OFFSET $offset", $params);
foreach ($rows as &$r) {
    $r['account'] = decrypt_field($r['account_enc']) ?? '(복호화 실패)';
}
unset($r);

staff_audit((int)$staff['id'], 'refund.list.view', 'page:' . $page, '환불 현황 열람 ' . count($rows) . '건');

render_admin('reg_refund', [
    'pageTitle' => '채권환불 신청현황',
    'staff'     => $staff,
    'complexes' => $complexes,
    'f'         => $f,
    'rows'      => $rows,
    'total'     => $total,
    'page'      => $page,
    'pages'     => $pages,
    'notice'    => $notice,
    'error'     => $error,
]);
