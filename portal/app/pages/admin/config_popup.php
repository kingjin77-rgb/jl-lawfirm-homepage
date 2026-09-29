<?php
/** 팝업 관리 — 제목/내용/노출기간/사용여부 (setting 키-값, 손님 홈 노출). */

$staff = staff_require();

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $enabled = !empty($_POST['popup_enabled']) ? '1' : '0';
    $title   = trim((string)($_POST['popup_title'] ?? ''));
    $body    = trim((string)($_POST['popup_body'] ?? ''));
    $start   = trim((string)($_POST['popup_start'] ?? ''));
    $end     = trim((string)($_POST['popup_end'] ?? ''));

    if (($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start))
     || ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))) {
        $error = '노출기간 날짜 형식이 올바르지 않습니다.';
    } elseif ($start !== '' && $end !== '' && $start > $end) {
        $error = '노출 종료일이 시작일보다 빠릅니다.';
    } elseif ($enabled === '1' && $title === '' && $body === '') {
        $error = '사용하려면 제목이나 내용을 넣어 주십시오.';
    } else {
        setting_set('popup_enabled', $enabled);
        setting_set('popup_title', $title);
        setting_set('popup_body', $body);
        setting_set('popup_start', $start);
        setting_set('popup_end', $end);
        staff_audit((int)$staff['id'], 'setting.save', 'setting:popup',
            '팝업 ' . ($enabled === '1' ? '사용' : '중지') . ($title !== '' ? " 「{$title}」" : ''));
        $saved = true;
    }
}

$values = setting_get_all();
$active = popup_active($values);

render_admin('config_popup', [
    'pageTitle' => '팝업 관리',
    'staff'     => $staff,
    'values'    => $values,
    'saved'     => $saved,
    'error'     => $error,
    'active'    => $active,
]);
