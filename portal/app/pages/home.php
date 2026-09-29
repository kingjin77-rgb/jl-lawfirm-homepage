<?php
/**
 * 손님 메인 — 기존 6개 카드 + 3단계 추가분:
 * 진행 중 설문/등기접수/위임장 참여 카드, 운영정보 카드, 알림 팝업.
 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

// 이 단지를 대상으로 진행 중인 참여 건 + 이 세대의 제출 여부
$participations = survey_open_for_complex((int)$hh['complex_id']);
foreach ($participations as &$p) {
    $p['responded'] = db_row('SELECT id FROM survey_response WHERE survey_id = ? AND household_id = ?',
        [(int)$p['id'], $customer['household_id']]) !== null;
}
unset($p);

$settings = settings_all();
$popup = popup_active($settings);
$ops = array_filter([
    '상담 가능 시간' => trim((string)($settings['ops_hours'] ?? '')),
    '점심시간'       => trim((string)($settings['ops_lunch'] ?? '')),
    '휴무 안내'      => trim((string)($settings['ops_holiday'] ?? '')),
], fn($v) => $v !== '');
$opsNotice = trim((string)($settings['ops_notice'] ?? ''));

render('home', [
    'pageTitle'      => '나의 등기',
    'customer'       => $customer,
    'hh'             => $hh,
    'participations' => $participations,
    'popup'          => $popup,
    'ops'            => $ops,
    'opsNotice'      => $opsNotice,
]);
