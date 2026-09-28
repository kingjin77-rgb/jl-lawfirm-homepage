<?php
/**
 * 등기진행 현황 — 검색 필터 + 8단계 완료표시 목록. 30건/쪽, 총건수.
 * 공동명의는 이름 여러 줄 + 생년월일 병기 (기존 화면 그대로).
 */

$staff = staff_require();

$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');
$f = reg_filters_from_get();
$res = reg_fetch_page($f, (int)($_GET['page'] ?? 1));

render_admin('reg_list', [
    'pageTitle' => '등기진행 현황',
    'staff'     => $staff,
    'complexes' => $complexes,
    'f'         => $f,
    'rows'      => $res['rows'],
    'total'     => $res['total'],
    'page'      => $res['page'],
    'pages'     => $res['pages'],
]);
