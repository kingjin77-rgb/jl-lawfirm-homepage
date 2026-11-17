<?php
/**
 * 지금 파기 — 파기예정일 전이라도 응답·답·서명을 지운다 (집계 수치만 남김).
 * admin 전용(세대 삭제와 같은 급) · 최근 10분 OTP 재확인 · 확인 체크 + 확인창 · staff_audit.
 * 자동 파기는 웹크론 /cron/purge 가 한다 (app/cron.php).
 */

$staff = staff_require_admin();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);
$base = $typeInfo['base'];

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$survey = survey_get($id, $surveyType);
if ($survey === null) {
    http_response_code(404);
    render_admin('error', ['pageTitle' => $typeInfo['label'] . ' 없음', 'staff' => $staff,
        'message' => '해당 ' . $typeInfo['label'] . '을(를) 찾을 수 없습니다.']);
    exit;
}
if (!empty($survey['purged_at'])) {
    redirect($base . '/result?id=' . $id);
}
// GET·POST 모두 — POST 때 재인증이 만료됐으면 확인 화면으로 돌아오게 한다.
staff_require_reauth($base . '/purge?id=' . $id);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (empty($_POST['confirm'])) {
        $error = '되돌릴 수 없다는 확인란에 체크해 주십시오.';
    } else {
        $r = survey_purge($id);
        if ($r !== null) {
            staff_audit((int)$staff['id'], 'survey.purge', 'survey:' . $id,
                "「{$survey['title']}」 지금 파기 — 응답 {$r['responses']}건·답 {$r['answers']}건 삭제 (집계만 보존)");
        }
        redirect($base . '/result?id=' . $id . '&purged=1');
    }
}

[$counted] = survey_attach_counts([$survey]);
render_admin('survey_purge', [
    'pageTitle'  => '지금 파기 — ' . $survey['title'],
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'survey'     => $counted,
    'error'      => $error,
]);
