<?php
/** 운영 정보 설정 — setting 키-값 (ops_hours/ops_lunch/ops_holiday/ops_notice). */

$staff = staff_require();

$fields = [
    'ops_hours'   => '상담 가능 시간',
    'ops_lunch'   => '점심시간',
    'ops_holiday' => '휴무 안내',
];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    foreach ($fields as $key => $label) {
        setting_set($key, trim((string)($_POST[$key] ?? '')));
    }
    setting_set('ops_notice', trim((string)($_POST['ops_notice'] ?? '')));
    staff_audit((int)$staff['id'], 'setting.save', 'setting:ops', '운영 정보 설정 저장');
    $saved = true;
}

$values = setting_get_all();

render_admin('config_ops', [
    'pageTitle' => '운영 정보 설정',
    'staff'     => $staff,
    'fields'    => $fields,
    'values'    => $values,
    'saved'     => $saved,
]);
