<?php
/**
 * 결과보기 — 문항별 집계. 객관식은 보기별 응답수·비율, 주관식/핸드폰은
 * 답 목록(최근순), 설명글은 집계 없음.
 */

$staff = staff_require();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);

$id = (int)($_GET['id'] ?? 0);
$survey = survey_get($id, $surveyType);
if ($survey === null) {
    http_response_code(404);
    render_admin('error', ['pageTitle' => '결과 없음', 'staff' => $staff,
        'message' => '해당 ' . $typeInfo['label'] . '을(를) 찾을 수 없습니다.']);
    exit;
}

[$counted] = survey_attach_counts([$survey]);
$questions = survey_questions($id);

// 문항별 집계 — 파기된 건은 파기 직전에 남긴 수치를 쓴다(개인 응답은 없다).
$summary = survey_purged_summary($survey);
$tally = $summary !== null ? (array)($summary['questions'] ?? []) : survey_tally($questions);
foreach ($questions as &$q) {
    $qid = (int)$q['id'];
    if ($q['qtype'] === 'note') {
        continue;
    }
    $t = (array)($tally[$qid] ?? []);
    $q['answered'] = (int)($t['answered'] ?? 0);
    if ($q['qtype'] === 'choice') {
        $q['tally'] = [];
        foreach ($q['options'] as $opt) {
            $q['tally'][$opt] = (int)($t['tally'][$opt] ?? 0);
        }
    } elseif ($q['qtype'] !== 'signature') {           // 서명은 건수만 — 이미지는 참여데이타 「보기」에서
        $q['texts'] = $summary !== null ? [] : db_all(
            'SELECT a.value, r.owner_name, h.dong, h.ho
               FROM survey_answer a
               JOIN survey_response r ON r.id = a.response_id
               JOIN household h ON h.id = r.household_id
              WHERE a.question_id = ? ORDER BY a.id DESC', [$qid]);
    }
}
unset($q);

render_admin('survey_result', [
    'pageTitle'  => '결과보기 — ' . $survey['title'],
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'survey'     => $counted,
    'questions'  => $questions,
    'daysLeft'   => survey_days_left($survey['retention_until'] ?? null),
]);
