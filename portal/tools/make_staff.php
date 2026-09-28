<?php
/**
 * 직원 계정 SQL 생성기 (CLI 전용) — 비밀번호를 코드·시드에 하드코딩하지
 * 않기 위해, 비밀번호를 인자로 받아 해시가 든 SQL 을 찍어준다.
 *
 * 사용법:
 *   php portal/tools/make_staff.php <login_id> <이름> <admin|staff> <비밀번호>
 * 예:
 *   php portal/tools/make_staff.php admin1 관리자 admin '비밀번호'
 * 나온 SQL 을 DB 에서 실행하면 된다.
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI 에서만 실행합니다.');
}
if ($argc < 5) {
    fwrite(STDERR, "사용법: php make_staff.php <login_id> <이름> <admin|staff> <비밀번호>\n");
    exit(1);
}

[, $loginId, $name, $role, $password] = $argv;

if (!in_array($role, ['admin', 'staff'], true)) {
    fwrite(STDERR, "role 은 admin 또는 staff 만 됩니다.\n");
    exit(1);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "비밀번호는 10자 이상으로 해 주십시오.\n");
    exit(1);
}

$hash = defined('PASSWORD_ARGON2ID')
    ? password_hash($password, PASSWORD_ARGON2ID)
    : password_hash($password, PASSWORD_BCRYPT);

// 홑따옴표만 이스케이프하면 되는 값들이지만, 안전하게 전부 처리한다.
$q = fn(string $s): string => str_replace(['\\', "'"], ['\\\\', "\\'"], $s);

echo "-- {$loginId} ({$role}) 계정 등록/갱신 SQL\n";
echo "INSERT INTO staff (login_id, name, role, pass_hash)\n";
echo "VALUES ('{$q($loginId)}', '{$q($name)}', '{$role}', '{$q($hash)}')\n";
echo "ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role),\n";
echo "  pass_hash = VALUES(pass_hash), failed_count = 0, locked_until = NULL;\n";
