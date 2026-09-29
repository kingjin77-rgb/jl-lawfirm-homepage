<?php
/**
 * 참여자 SMS — member_sms 와 같은 방식(발송 업체 연동 전, sms_log 「대기」 저장만).
 * 대상: 이 설문의 참여 세대 / 불참 세대 / 대상 단지 전체. 핸드폰이 있는
 * 명의인 기준(같은 번호는 1건). 참여 대상은 제출 때 적은 핸드폰도 포함한다.
 */

$staff = staff_require();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);

$surveys = db_all('SELECT id, title FROM survey WHERE type = ? ORDER BY id DESC', [$surveyType]);
$selId = (int)($_GET['id'] ?? ($_POST['survey_id'] ?? 0));
$notice = '';
$error = '';

/** 대상 세대의 서로 다른 핸드폰 수 — 명의인 번호 + (참여면) 응답 핸드폰 */
function survey_sms_phone_count(int $surveyId, string $audience): int
{
    if ($audience === 'joined') {
        $hhSql = 'SELECT household_id FROM survey_response WHERE survey_id = ?';
        $params = [$surveyId];
    } elseif ($audience === 'absent') {
        $hhSql = 'SELECT h.id FROM household h
                   WHERE h.complex_id IN (SELECT complex_id FROM survey_target WHERE survey_id = ?)
                     AND NOT EXISTS (SELECT 1 FROM survey_response r
                                      WHERE r.survey_id = ? AND r.household_id = h.id)';
        $params = [$surveyId, $surveyId];
    } else {
        $hhSql = 'SELECT h.id FROM household h
                   WHERE h.complex_id IN (SELECT complex_id FROM survey_target WHERE survey_id = ?)';
        $params = [$surveyId];
    }
    $phones = [];
    foreach (db_all("SELECT DISTINCT o.phone FROM owner o WHERE o.phone <> ''
                      AND o.household_id IN ($hhSql)", $params) as $r) {
        $phones[preg_replace('/\D/', '', $r['phone'])] = true;
    }
    if ($audience !== 'absent') {
        foreach (db_all("SELECT DISTINCT phone FROM survey_response
                          WHERE survey_id = ? AND phone <> ''", [$surveyId]) as $r) {
            $phones[preg_replace('/\D/', '', $r['phone'])] = true;
        }
    }
    unset($phones['']);
    return count($phones);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $body     = trim((string)($_POST['body'] ?? ''));
    $audience = (string)($_POST['audience'] ?? 'all');
    if (!in_array($audience, ['joined', 'absent', 'all'], true)) {
        $audience = 'all';
    }
    $survey = survey_get($selId, $surveyType);
    $bytes = sms_byte_length($body);

    if ($survey === null) {
        $error = '대상 ' . $typeInfo['label'] . '을(를) 선택해 주십시오.';
    } elseif ($body === '') {
        $error = '보낼 내용을 넣어 주십시오.';
    } elseif ($bytes > 2000) {
        $error = "장문(LMS)은 2,000바이트까지입니다. 지금 {$bytes}바이트입니다.";
    } else {
        $cnt = survey_sms_phone_count($selId, $audience);
        if ($cnt === 0) {
            $error = '조건에 맞는 수신 대상(핸드폰 보유)이 없습니다.';
        } else {
            $msgType = $bytes <= 90 ? 'SMS' : 'LMS';
            $audLabel = ['joined' => '참여', 'absent' => '불참', 'all' => '전체'][$audience];
            db_exec('INSERT INTO sms_log (staff_id, survey_id, audience, msg_type, sender, body, target_cnt, status)
                     VALUES (?,?,?,?,?,?,?,?)',
                [(int)$staff['id'], $selId, $audience, $msgType, SMS_SENDER, $body, $cnt, '대기']);
            staff_audit((int)$staff['id'], 'sms.queue', 'sms:' . db_insert_id(),
                "{$msgType} {$cnt}명 ({$typeInfo['label']} #{$selId} {$audLabel})");
            $notice = "{$msgType} {$cnt}명({$audLabel}) 대상 발송 건을 대기 목록에 저장했습니다.";
        }
    }
}

render_admin('survey_sms', [
    'pageTitle'  => '참여자 SMS — ' . $typeInfo['label'],
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'surveys'    => $surveys,
    'selId'      => $selId,
    'notice'     => $notice,
    'error'      => $error,
]);
