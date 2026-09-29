<?php
/**
 * 참여대상자 — 아파트(단지) 단위 선택. 대상 단지의 전 세대가 모수가 된다.
 */

$staff = staff_require();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$survey = survey_get($id, $surveyType);
if ($survey === null) {
    http_response_code(404);
    render_admin('error', ['pageTitle' => '대상 없음', 'staff' => $staff,
        'message' => '해당 ' . $typeInfo['label'] . '을(를) 찾을 수 없습니다.']);
    exit;
}

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $picked = array_values(array_unique(array_map('intval', (array)($_POST['complex_ids'] ?? []))));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_exec('DELETE FROM survey_target WHERE survey_id = ?', [$id]);
        foreach ($picked as $cid) {
            if ($cid > 0 && db_row('SELECT id FROM complex WHERE id = ?', [$cid]) !== null) {
                db_exec('INSERT INTO survey_target (survey_id, complex_id) VALUES (?,?)', [$id, $cid]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    staff_audit((int)$staff['id'], 'survey.target', 'survey:' . $id,
        '대상 단지 ' . count($picked) . '곳');
    $notice = '참여대상자를 저장했습니다. 대상 단지 세대의 손님 화면에 곧바로 노출됩니다.';
}

$targets = survey_target_ids($id);
$complexes = db_all(
    'SELECT c.id, c.name, (SELECT COUNT(*) FROM household h WHERE h.complex_id = c.id) AS hh_cnt
       FROM complex c ORDER BY c.sort, c.id');

render_admin('survey_target', [
    'pageTitle'  => '참여대상자 — ' . $survey['title'],
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'survey'     => $survey,
    'targets'    => $targets,
    'complexes'  => $complexes,
    'notice'     => $notice,
]);
