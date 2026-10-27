<?php
/**
 * 직원 계정 관리 — admin 권한 전용. 등록·권한 변경·비밀번호 재설정·중지.
 * 비밀번호는 화면·기록 어디에도 원문을 남기지 않는다.
 */

$staff = staff_require_admin();
staff_require_reauth();          // 직원계정 관리는 최근 10분 내 OTP 재확인 필요

$notice = '';
$error = '';
$enrollOut = null;        // ['login_id'=>, 'code'=>] — 발급한 등록코드는 이 화면에 한 번만 나온다

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
            $newId = db_insert_id();
            staff_audit((int)$staff['id'], 'staff.create', 'staff:' . $newId, "$loginId ($role)");
            $enrollOut = ['login_id' => $loginId, 'code' => staff_enroll_issue($newId)];
            staff_audit((int)$staff['id'], 'staff.enroll_issue', 'staff:' . $newId, '등록코드 발급 (계정 생성)');
            $notice = "직원 계정 {$loginId} 를 등록했습니다. 아래 등록코드를 본인에게 직접 알려 주십시오.";
        }
    } elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$staff['id']) {
            $error = '내 계정은 중지할 수 없습니다.';
        } else {
            db_exec('UPDATE staff SET is_active = 1 - is_active, auth_version = auth_version + 1 WHERE id = ?', [$id]);
            staff_audit((int)$staff['id'], 'staff.toggle', "staff:$id", '사용 상태 전환');
            $notice = '사용 상태를 바꿨습니다.';
        }
    } elseif ($act === 'passwd') {
        $id = (int)($_POST['id'] ?? 0);
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 10) {
            $error = '비밀번호는 10자 이상으로 해 주십시오.';
        } else {
            db_exec('UPDATE staff SET pass_hash = ?, failed_count = 0, locked_until = NULL,
                     auth_version = auth_version + 1 WHERE id = ?',
                [staff_hash_password($pw), $id]);
            staff_audit((int)$staff['id'], 'staff.passwd', "staff:$id", '비밀번호 재설정·잠금 해제');
            $notice = '비밀번호를 재설정하고 잠금을 풀었습니다. (그 직원의 기존 로그인은 끊깁니다)';
        }
    } elseif ($act === 'otp_reset') {
        // 휴대폰 분실·교체 — 비밀키·복구코드 삭제, 기존 세션 끊기. 다음 로그인 때 새로 등록한다.
        $id = (int)($_POST['id'] ?? 0);
        $target = db_row('SELECT login_id FROM staff WHERE id = ?', [$id]);
        if ($target === null) {
            $error = '직원을 찾을 수 없습니다.';
        } else {
            staff_totp_reset($id);
            staff_audit((int)$staff['id'], 'staff.otp_reset', "staff:$id", "{$target['login_id']} OTP·복구코드 초기화");
            $enrollOut = ['login_id' => $target['login_id'], 'code' => staff_enroll_issue($id)];
            staff_audit((int)$staff['id'], 'staff.enroll_issue', "staff:$id", '등록코드 발급 (OTP 초기화)');
            $notice = "{$target['login_id']} 의 OTP 를 초기화했습니다. 다음 로그인 때 아래 등록코드로 새로 등록합니다.";
            if ($id === (int)$staff['id']) {
                session_end_role('staff');
                redirect('/admin/login');
            }
        }
    } elseif ($act === 'enroll_issue') {
        $id = (int)($_POST['id'] ?? 0);
        $target = db_row('SELECT login_id FROM staff WHERE id = ?', [$id]);
        if ($target === null) {
            $error = '직원을 찾을 수 없습니다.';
        } elseif (staff_totp_enabled($id)) {
            $error = '이미 OTP 를 쓰는 계정입니다. 휴대폰을 바꿨으면 「OTP 초기화」를 누르십시오.';
        } else {
            $enrollOut = ['login_id' => $target['login_id'], 'code' => staff_enroll_issue($id)];
            staff_audit((int)$staff['id'], 'staff.enroll_issue', "staff:$id", '등록코드 발급');
            $notice = "{$target['login_id']} 의 등록코드를 새로 발급했습니다 (이전 코드는 무효).";
        }
    }
}

$list = db_all('SELECT s.id, s.login_id, s.name, s.role, s.is_active, s.locked_until, s.created_at,
                       t.enabled AS otp_on,
                       (SELECT COUNT(*) FROM staff_recovery_code r WHERE r.staff_id = s.id AND r.used_at IS NULL) AS rc_left
                  FROM staff s LEFT JOIN staff_totp t ON t.staff_id = s.id ORDER BY s.id');

render_admin('config_staff', [
    'pageTitle' => '직원 계정 관리',
    'staff'     => $staff,
    'list'      => $list,
    'notice'    => $notice,
    'error'     => $error,
    'enrollOut' => $enrollOut,
]);
