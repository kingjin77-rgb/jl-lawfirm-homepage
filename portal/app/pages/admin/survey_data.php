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
    staff_require_reauth();      // CSV 내보내기는 최근 10분 내 OTP 재확인 필요
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
                $v = $answersBy[(int)$r['response_id']][(int)$q['id']] ?? '';
                $line[] = $q['qtype'] === 'signature' ? ($v !== '' ? '서명 있음' : '') : $v;   // 서명 이미지는 내보내지 않는다
            }
            fputcsv($out, $line);
        }
    }
    fclose($out);
    exit;
}

$reveal = reveal_guard($staff, 'survey.data');
// 서명은 「보기」(재인증 통과) 때만 복호화한다. 몇 건을 풀었는지 따로 남긴다.
$signatures = [];
if ($reveal !== null && $mode === 'joined') {
    foreach ($questions as $q) {
        if ($q['qtype'] !== 'signature') {
            continue;
        }
        foreach ($answersBy as $rid => $ans) {
            if (isset($ans[(int)$q['id']]) && reveal_hit($reveal, (int)$rid)) {
                $signatures[$rid][(int)$q['id']] = survey_signature_data_uri($ans[(int)$q['id']]);
            }
        }
    }
    if ($signatures !== []) {
        staff_audit((int)$staff['id'], 'survey.signature.reveal', 'survey:' . $id,
            '서명 ' . array_sum(array_map('count', $signatures)) . '건 복호화 열람 ('
            . ($reveal === 'all' ? '이 쪽 전체' : '응답 ' . $reveal) . ')');
    }
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
    'reveal'     => $reveal,
    'signatures' => $signatures,
]);
