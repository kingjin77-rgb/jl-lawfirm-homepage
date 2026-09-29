<?php
/**
 * 미리보기 — 손님 참여 폼과 같은 배치로 문항을 보여준다 (제출 없음).
 */

$staff = staff_require();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);

$id = (int)($_GET['id'] ?? 0);
$survey = survey_get($id, $surveyType);
if ($survey === null) {
    http_response_code(404);
    render_admin('error', ['pageTitle' => '미리보기 없음', 'staff' => $staff,
        'message' => '해당 ' . $typeInfo['label'] . '을(를) 찾을 수 없습니다.']);
    exit;
}

render_admin('survey_preview', [
    'pageTitle'  => '미리보기 — ' . $survey['title'],
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'survey'     => $survey,
    'questions'  => survey_questions($id),
]);
