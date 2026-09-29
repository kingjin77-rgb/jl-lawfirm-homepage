<?php
/**
 * 참여데이타/불참데이타 — mode=joined(기본)/absent. export=csv 로 내려받기.
 * 참여 CSV 는 문항 답까지 열로 붙는다. 핸드폰 답은 화면·CSV 모두 그대로
 * 보여준다(직원 열람용 — 기존 시스템과 동일).
 */

$staff = staff_require();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);

$id = (int)($_GET['id'] ?? 0);
$survey = survey_get($id, $surveyType);
if ($survey === null) {
    http_response_code(404);
    render_admin('error', ['pageTitle' => '데이타 없음', 'staff' => $staff,
        'message' => '해당 ' . $typeInfo['label'] . '을(를) 찾을 수 없습니다.']);
    exit;
}

$mode = ($_GET['mode'] ?? 'joined') === 'absent' ? 'absent' : 'joined';
$rows = survey_data_rows($id, $mode);
$questions = array_values(array_filter(survey_questions($id), fn($q) => $q['qtype'] !== 'note'));
$answersBy = $mode === 'joined' ? survey_answers_by_response($id) : [];

if (($_GET['export'] ?? '') === 'csv') {
    staff_audit((int)$staff['id'], 'survey.export', 'survey:' . $id,
        ($mode === 'absent' ? '불참' : '참여') . ' ' . count($rows) . '행 내보내기');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $surveyType . '_' . $id . '_'
        . ($mode === 'absent' ? 'absent' : 'joined') . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                     // UTF-8 BOM — 엑셀 한글 대응
    if ($mode === 'absent') {
        fputcsv($out, ['아파트', '동', '호수', '명의인']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['complex_name'], $r['dong'], $r['ho'], $r['owner_names']]);
        }
    } else {
        $head = ['아파트', '동', '호수', '제출자', '핸드폰', '제출일시'];
        foreach ($questions as $q) {
            $head[] = $q['title'];
        }
        fputcsv($out, $head);
        foreach ($rows as $r) {
            $line = [$r['complex_name'], $r['dong'], $r['ho'], $r['owner_name'],
                     $r['phone'], $r['submitted_at']];
            foreach ($questions as $q) {
                $line[] = $answersBy[(int)$r['response_id']][(int)$q['id']] ?? '';
            }
            fputcsv($out, $line);
        }
    }
    fclose($out);
    exit;
}

render_admin('survey_data', [
    'pageTitle'  => ($mode === 'absent' ? '불참데이타' : '참여데이타') . ' — ' . $survey['title'],
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'survey'     => $survey,
    'mode'       => $mode,
    'rows'       => $rows,
    'questions'  => $questions,
    'answersBy'  => $answersBy,
]);
