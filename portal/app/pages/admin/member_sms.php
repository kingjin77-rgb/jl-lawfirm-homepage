<?php
/**
 * SMS 발송 — 발송 업체 연동 전이라 sms_log 에 상태 '대기' 로 저장만 한다.
 * 발신번호는 사전등록 번호(1899-4252) 고정. 대상은 아파트 전체 또는
 * 아파트+동+이름 검색 결과. 90byte 이하 SMS, 초과~2000byte LMS.
 */

$staff = staff_require();

$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $body      = trim((string)($_POST['body'] ?? ''));
    $complexId = (int)($_POST['complex_id'] ?? 0);
    $dong      = trim((string)($_POST['dong'] ?? ''));
    $name      = trim((string)($_POST['name'] ?? ''));

    $bytes = sms_byte_length($body);
    if ($body === '') {
        $error = '보낼 내용을 넣어 주십시오.';
    } elseif ($bytes > 2000) {
        $error = "장문(LMS)은 2,000바이트까지입니다. 지금 {$bytes}바이트입니다.";
    } elseif ($complexId <= 0) {
        $error = '대상 아파트를 선택해 주십시오.';
    } else {
        // 대상 집계 — 핸드폰이 있는 명의인 수 (중복 번호 제거)
        $where = ['h.complex_id = ?', "o.phone <> ''"];
        $params = [$complexId];
        if ($dong !== '') { $where[] = 'h.dong = ?'; $params[] = $dong; }
        if ($name !== '') { $where[] = 'o.name LIKE ?'; $params[] = "%$name%"; }
        $cnt = (int)(db_row(
            'SELECT COUNT(DISTINCT o.phone) AS n FROM owner o JOIN household h ON h.id = o.household_id
              WHERE ' . implode(' AND ', $where), $params)['n'] ?? 0);

        if ($cnt === 0) {
            $error = '조건에 맞는 수신 대상(핸드폰 보유)이 없습니다.';
        } else {
            $msgType = $bytes <= 90 ? 'SMS' : 'LMS';
            db_exec('INSERT INTO sms_log (staff_id, complex_id, msg_type, sender, body, target_cnt, status)
                     VALUES (?,?,?,?,?,?,?)',
                [(int)$staff['id'], $complexId, $msgType, SMS_SENDER, $body, $cnt, '대기']);
            staff_audit((int)$staff['id'], 'sms.queue', 'sms:' . db_insert_id(),
                "{$msgType} {$cnt}명 (" . ($dong !== '' || $name !== '' ? '검색결과' : '아파트 전체') . ')');
            $notice = "{$msgType} {$cnt}명 대상 발송 건을 대기 목록에 저장했습니다.";
        }
    }
}

render_admin('member_sms', [
    'pageTitle' => 'SMS 발송',
    'staff'     => $staff,
    'complexes' => $complexes,
    'notice'    => $notice,
    'error'     => $error,
]);
