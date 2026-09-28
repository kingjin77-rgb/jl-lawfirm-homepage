<?php
/**
 * 직원 계정 관리 — admin 권한 전용. 등록·권한 변경·비밀번호 재설정·중지.
 * 비밀번호는 화면·기록 어디에도 원문을 남기지 않는다.
 */

$staff = staff_require_admin();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'create') {
        $loginId = trim((string)($_POST['login_id'] ?? ''));
        $name    = trim((string)($_POST['name'] ?? ''));
        $role    = (string)($_POST['role'] ?? 'staff');
        $pw      = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $loginId)) {
            $error = '아이디는 영문·숫자·밑줄 3~50자로 해 주십시오.';
        } elseif ($name === '') {
            $error = '이름을 넣어 주십시오.';
        } elseif (!in_array($role, ['admin', 'staff'], true)) {
            $error = '권한 값이 올바르지 않습니다.';
        } elseif (strlen($pw) < 10) {
            $error = '비밀번호는 10자 이상으로 해 주십시오.';
        } elseif (db_row('SELECT id FROM staff WHERE login_id = ?', [$loginId]) !== null) {
            $error = '이미 있는 아이디입니다.';
        } else {
            db_exec('INSERT INTO staff (login_id, name, role, pass_hash) VALUES (?,?,?,?)',
                [$loginId, $name, $role, staff_hash_password($pw)]);
            staff_audit((int)$staff['id'], 'staff.create', 'staff:' . db_insert_id(), "$loginId ($role)");
            $notice = "직원 계정 {$loginId} 를 등록했습니다.";
        }
    } elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$staff['id']) {
            $error = '내 계정은 중지할 수 없습니다.';
        } else {
            db_exec('UPDATE staff SET is_active = 1 - is_active WHERE id = ?', [$id]);
            staff_audit((int)$staff['id'], 'staff.toggle', "staff:$id", '사용 상태 전환');
            $notice = '사용 상태를 바꿨습니다.';
        }
    } elseif ($act === 'passwd') {
        $id = (int)($_POST['id'] ?? 0);
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 10) {
            $error = '비밀번호는 10자 이상으로 해 주십시오.';
        } else {
            db_exec('UPDATE staff SET pass_hash = ?, failed_count = 0, locked_until = NULL WHERE id = ?',
                [staff_hash_password($pw), $id]);
            staff_audit((int)$staff['id'], 'staff.passwd', "staff:$id", '비밀번호 재설정·잠금 해제');
            $notice = '비밀번호를 재설정하고 잠금을 풀었습니다.';
        }
    }
}

$list = db_all('SELECT id, login_id, name, role, is_active, locked_until, created_at FROM staff ORDER BY id');

render_admin('config_staff', [
    'pageTitle' => '직원 계정 관리',
    'staff'     => $staff,
    'list'      => $list,
    'notice'    => $notice,
    'error'     => $error,
]);
