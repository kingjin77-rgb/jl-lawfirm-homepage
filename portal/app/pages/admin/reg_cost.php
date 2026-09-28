<?php
/** 등기비용 현황 — 11항목 + 합계/입금액/차액 전부 열로 (기존 tax_list 재현). */

$staff = staff_require();

$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');
$f = reg_filters_from_get();
$res = reg_fetch_page($f, (int)($_GET['page'] ?? 1));

render_admin('reg_cost', [
    'pageTitle' => '등기비용 현황',
    'staff'     => $staff,
    'complexes' => $complexes,
    'f'         => $f,
    'rows'      => $res['rows'],
    'total'     => $res['total'],
    'page'      => $res['page'],
    'pages'     => $res['pages'],
]);
