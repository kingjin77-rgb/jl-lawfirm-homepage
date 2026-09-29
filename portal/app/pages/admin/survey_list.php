<?php
/**
 * 설문/등기접수/위임장 목록 — $surveyType 은 라우터(index.php)가 넘긴다.
 * 설문명 · 주최 · 총인원/참여/불참 · 작성일 + 행 단추 6개
 * (참여대상자 | 결과보기 | 참여데이타 | 불참데이타 | 미리보기 | 조회/수정).
 * 등기접수는 시작일/종료일 열이 추가된다.
 */

$staff = staff_require();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);

$perPage = 30;
$total = (int)(db_row('SELECT COUNT(*) AS n FROM survey WHERE type = ?', [$surveyType])['n'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $pages));
$offset = ($page - 1) * $perPage;

$rows = db_all("SELECT * FROM survey WHERE type = ? ORDER BY id DESC LIMIT $perPage OFFSET $offset",
    [$surveyType]);
$rows = survey_attach_counts($rows);

render_admin('survey_list', [
    'pageTitle'  => $typeInfo['label'] . ' 목록',
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'rows'       => $rows,
    'total'      => $total,
    'page'       => $page,
    'pages'      => $pages,
]);
