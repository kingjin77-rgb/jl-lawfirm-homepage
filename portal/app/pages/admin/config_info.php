<?php
/** 기본 정보 설정 — setting 키-값 읽기/쓰기. 항목은 기존 화면 그대로. */

$staff = staff_require();

$fields = firm_setting_fields();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    foreach ($fields as $key => $label) {
        setting_set($key, trim((string)($_POST[$key] ?? '')));
    }
    staff_audit((int)$staff['id'], 'setting.save', 'setting:firm', '기본 정보 설정 저장');
    $saved = true;
}

$values = setting_get_all();

render_admin('config_info', [
    'pageTitle' => '기본 정보 설정',
    'staff'     => $staff,
    'fields'    => $fields,
    'values'    => $values,
    'saved'     => $saved,
]);
