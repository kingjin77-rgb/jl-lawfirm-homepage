<?php
/**
 * 인증 — 직원(아이디/비밀번호 → OTP)과 손님(아파트+동+호+이름+생년월일6 → 문자 인증) 두 갈래.
 * 직원: 5회 실패 → 15분 잠금 (staff.failed_count / locked_until) + TOTP 2단계 (totp.php)
 * 손님: 세대(단지+동+호) 기준 1시간 10회 실패 → 잠금 (login_log 집계) + 문자 인증 2단계 (sms.php)
 * 공통: IP 단위 차단 (security.php — 10분 내 서로 다른 대상 5곳 / 30회 실패 → 1시간)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/totp.php';

const STAFF_MAX_FAILS      = 5;
const STAFF_LOCK_MINUTES   = 15;
const STAFF_OTP_MAX_FAILS  = 5;      // 계정 단위 OTP·복구코드·등록코드 실패
const STAFF_OTP_LOCK_MINUTES = 15;
const CUSTOMER_MAX_FAILS   = 10;
const CUSTOMER_LOCK_WINDOW = 60; // 분

const IPBLOCK_MESSAGE = '이 접속 위치에서 확인 실패가 너무 많아 1시간 동안 막았습니다. 급하시면 1899-4252 로 전화해 주십시오.';

function client_ip(): string
{
    // 가비아 공유호스팅은 프록시 없이 REMOTE_ADDR 이 실제 IP 다.
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

/* ------------------------------------------------------------------ */
/* 직원 인증                                                            */
/* ------------------------------------------------------------------ */

/** 해시 생성 — argon2id 우선, 없으면 bcrypt 로 폴백 */
function staff_hash_password(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * 직원 1단계(비밀번호). 통과하면 세션에 staff_pending 만 두고 OTP 화면으로 보낸다.
 * @return array{ok:bool, error:string, next:string}
 */
function staff_login(string $loginId, string $password): array
{
    if (ip_blocked_for_staff(client_ip()) !== null) {
        return ['ok' => false, 'error' => IPBLOCK_MESSAGE, 'next' => ''];
    }
    $staff = db_row('SELECT * FROM staff WHERE login_id = ? AND is_active = 1', [$loginId]);

    if ($staff !== null && $staff['locked_until'] !== null
        && strtotime($staff['locked_until']) > time()) {
        return ['ok' => false, 'next' => '',
            'error' => '로그인이 잠시 잠겼습니다. ' . STAFF_LOCK_MINUTES . '분 뒤 다시 시도해 주십시오.'];
    }

    // 계정이 없어도 같은 비용의 검증을 돌려 계정 존재 여부가 시간으로 새지 않게 한다.
    static $dummy = null;
    $dummy ??= staff_hash_password('jl-portal-dummy-password');
    $hash = (string)($staff['pass_hash'] ?? '');
    $ok = password_verify($password, $hash !== '' ? $hash : $dummy) && $hash !== '';

    if (!$ok) {
        if ($staff !== null) {
            $fails = (int)$staff['failed_count'] + 1;
            $lock = $fails >= STAFF_MAX_FAILS
                ? date('Y-m-d H:i:s', time() + STAFF_LOCK_MINUTES * 60) : null;
            db_exec('UPDATE staff SET failed_count = ?, locked_until = ? WHERE id = ?',
                [$fails >= STAFF_MAX_FAILS ? 0 : $fails, $lock, $staff['id']]);
        }
        auth_fail_record('staff', null, '', '', $loginId);
        return ['ok' => false, 'next' => '', 'error' => '아이디 또는 비밀번호가 맞지 않습니다.'];
    }

    db_exec('UPDATE staff SET failed_count = 0, locked_until = NULL WHERE id = ?', [$staff['id']]);
    if (password_needs_rehash($hash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        db_exec('UPDATE staff SET pass_hash = ? WHERE id = ?', [staff_hash_password($password), $staff['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['staff_pending'] = ['id' => (int)$staff['id'], 'at' => time()];
    staff_audit((int)$staff['id'], 'staff.login.password', 'staff:' . $staff['id'], '비밀번호 통과 — OTP 대기');
    return ['ok' => true, 'error' => '',
        'next' => staff_totp_enabled((int)$staff['id']) ? '/admin/otp' : '/admin/otp/setup'];
}

/** 대기 중(비밀번호만 통과)인 직원 행 */
function staff_pending(): ?array
{
    $p = $_SESSION['staff_pending'] ?? null;
    if ($p === null) {
        return null;
    }
    return db_row('SELECT * FROM staff WHERE id = ? AND is_active = 1', [(int)$p['id']]);
}

/** 2단계 통과 — 직원 세션 시작 (세션ID 재발급) */
function staff_complete_login(array $staff, string $how): void
{
    session_begin_role('staff');
    $_SESSION['staff'] = [
        'id'     => (int)$staff['id'],
        'name'   => $staff['name'],
        'role'   => $staff['role'],
        'av'     => (int)($staff['auth_version'] ?? 1),
        'otp_at' => time(),
    ];
    unset($_SESSION['staff_pending'], $_SESSION['staff_setup']);
    auth_ok_record('staff_otp', null, '', '', 'staff:' . $staff['id']);
    staff_audit((int)$staff['id'], 'staff.login', 'staff:' . $staff['id'], $how);
}

/**
 * 직원 OTP(또는 복구코드) 확인 — 로그인 2단계·재인증 공용.
 * @return array{ok:bool, error:string, via:string}
 */
function staff_otp_check(int $staffId, string $input, bool $allowRecovery): array
{
    if (ip_blocked_for_staff(client_ip()) !== null) {
        return ['ok' => false, 'error' => IPBLOCK_MESSAGE, 'via' => ''];
    }
    // 계정 단위 잠금 — IP 를 바꿔도, 비밀번호를 다시 넣어도 풀리지 않는다.
    $lock = db_row('SELECT fail_until > NOW() AS locked FROM staff_totp WHERE staff_id = ?', [$staffId]);
    if ((int)($lock['locked'] ?? 0) === 1) {
        return ['ok' => false, 'via' => '',
            'error' => 'OTP 를 ' . STAFF_OTP_MAX_FAILS . '회 틀려 ' . STAFF_OTP_LOCK_MINUTES . '분 동안 잠갔습니다. 잠시 뒤 다시 시도하시거나 admin 직원에게 문의해 주십시오.'];
    }
    $input = trim($input);
    $digits = preg_replace('/\D/', '', $input);
    if (strlen($digits) === TOTP_DIGITS && strlen(preg_replace('/[\s\-]/', '', $input)) === TOTP_DIGITS) {
        if (staff_totp_verify($staffId, $digits)) {
            db_exec('UPDATE staff_totp SET fail_count = 0, fail_until = NULL WHERE staff_id = ?', [$staffId]);
            return ['ok' => true, 'error' => '', 'via' => 'otp'];
        }
    } elseif ($allowRecovery && $input !== '' && recovery_code_use($staffId, $input)) {
        db_exec('UPDATE staff_totp SET fail_count = 0, fail_until = NULL WHERE staff_id = ?', [$staffId]);
        staff_audit($staffId, 'staff.recovery.use', 'staff:' . $staffId, '복구코드 사용 (남은 ' . recovery_codes_left($staffId) . '개)');
        return ['ok' => true, 'error' => '', 'via' => 'recovery'];
    }
    staff_otp_fail($staffId);
    return ['ok' => false, 'via' => '',
        'error' => '코드가 맞지 않습니다. 이미 쓴 코드는 다시 쓸 수 없으니 앱에 새 코드가 나오면 넣어 주십시오.'];
}

/** OTP 실패 1회 — 계정 카운터(원자적 증가) + IP 원장. 5회째에 15분 잠금. */
function staff_otp_fail(int $staffId): void
{
    db_exec('UPDATE staff_totp SET fail_count = fail_count + 1 WHERE staff_id = ?', [$staffId]);
    $n = db_exec('UPDATE staff_totp SET fail_count = 0, fail_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                   WHERE staff_id = ? AND fail_count >= ?', [STAFF_OTP_LOCK_MINUTES, $staffId, STAFF_OTP_MAX_FAILS]);
    if ($n === 1) {
        staff_audit($staffId, 'staff.otp.locked', 'staff:' . $staffId, 'OTP ' . STAFF_OTP_MAX_FAILS . '회 실패 — ' . STAFF_OTP_LOCK_MINUTES . '분 잠금');
    }
    auth_fail_record('staff_otp', null, '', '', 'staff:' . $staffId);
}

function staff_current(): ?array
{
    return $_SESSION['staff'] ?? null;
}

/** 직원 작업기록 — 관리자 화면의 모든 쓰기 작업에서 부른다. */
function staff_audit(?int $staffId, string $action, string $target = '', string $detail = ''): void
{
    db_exec('INSERT INTO staff_audit (staff_id, action, target, detail, ip) VALUES (?,?,?,?,?)',
        [$staffId, $action, $target, $detail, client_ip()]);
}

/* ------------------------------------------------------------------ */
/* 손님 인증                                                            */
/* ------------------------------------------------------------------ */

/** 이름 정규화 — NFC 통일 + 공백류 제거 ("홍 길동", 자소분리 입력 대비) */
function normalize_name(string $name): string
{
    if (class_exists('Normalizer')) {
        $n = Normalizer::normalize($name, Normalizer::FORM_C);
        if ($n !== false) {
            $name = $n;
        }
    }
    return preg_replace('/\s+/u', '', trim($name));
}

/** 세대 잠금 여부 — 최근 1시간 손님 로그인 실패 횟수로 판단 */
function customer_locked(int $complexId, string $dong, string $ho): bool
{
    $row = db_row(
        "SELECT COUNT(*) AS n FROM login_log
          WHERE kind = 'customer' AND complex_id = ? AND dong = ? AND ho = ? AND ok = 0
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$complexId, $dong, $ho, CUSTOMER_LOCK_WINDOW]
    );
    return (int)($row['n'] ?? 0) >= CUSTOMER_MAX_FAILS;
}

/**
 * 손님 1단계 — 이름·생년월일 대조. 통과하면 등록 휴대폰으로 인증번호를 보내고
 * customer_pending 만 세션에 둔다 (아직 로그인 아님).
 * @return array{ok:bool, error:string}  error: 'lockout' | 'ipblock' | 'nophone' | 문구
 */
function customer_login(int $complexId, string $dong, string $ho, string $name, string $birth6): array
{
    $dong = trim($dong);
    $ho = trim($ho);
    $name = normalize_name($name);
    $birth6 = preg_replace('/\D/', '', $birth6);

    if (ip_blocked(client_ip()) !== null) {
        return ['ok' => false, 'error' => 'ipblock'];
    }
    if ($complexId <= 0 || $dong === '' || $ho === '' || $name === '' || strlen($birth6) !== 6) {
        return ['ok' => false, 'error' => '모든 항목을 채워 주십시오. 생년월일은 숫자 6자리입니다.'];
    }
    if (customer_locked($complexId, $dong, $ho)) {
        return ['ok' => false, 'error' => 'lockout'];
    }

    // 노출 중인 단지의 세대 → 소유자 이름+생년월일 대조 (공동명의는 둘 중 아무나)
    $owners = db_all(
        'SELECT o.id, o.name, o.phone, o.household_id
           FROM household h
           JOIN complex c ON c.id = h.complex_id AND c.exposed = 1
           JOIN owner o ON o.household_id = h.id
          WHERE h.complex_id = ? AND h.dong = ? AND h.ho = ? AND o.birth6 = ?',
        [$complexId, $dong, $ho, $birth6]
    );
    // 이름은 정규화해서 PHP 쪽에서 대조한다 (DB 콜레이션의 공백 처리에 기대지 않는다).
    $owner = null;
    foreach ($owners as $cand) {
        if (normalize_name($cand['name']) === $name) {
            $owner = $cand;
            break;
        }
    }

    if ($owner === null) {
        auth_fail_record('customer', $complexId, $dong, $ho);
        if (ip_blocked(client_ip()) !== null) {
            return ['ok' => false, 'error' => 'ipblock'];
        }
        if (customer_locked($complexId, $dong, $ho)) {
            return ['ok' => false, 'error' => 'lockout'];
        }
        return ['ok' => false, 'error' => '입력하신 내용과 일치하는 세대가 없습니다. 계약서에 적힌 분의 성함과 생년월일인지 확인해 주십시오.'];
    }
    auth_ok_record('customer', $complexId, $dong, $ho);

    // 2단계 — 그 명의인의 등록 휴대폰. 없으면 막고 등기센터로 안내한다.
    if (mobile_normalize((string)$owner['phone']) === null) {
        return ['ok' => false, 'error' => 'nophone'];
    }
    session_regenerate_id(true);
    $_SESSION['customer_pending'] = [
        'household_id' => (int)$owner['household_id'],
        'owner_id'     => (int)$owner['id'],
        'owner_name'   => $owner['name'],
        'complex_id'   => $complexId,
        'dong'         => $dong,
        'ho'           => $ho,
        'at'           => time(),
    ];
    customer_send_code('login');
    return ['ok' => true, 'error' => ''];
}

/** 인증번호 발송 — login(대기 세션) 또는 reauth(로그인 세션). 결과를 세션 안내로 남긴다. */
function customer_send_code(string $purpose): array
{
    $ctx = $purpose === 'login' ? ($_SESSION['customer_pending'] ?? null) : ($_SESSION['customer'] ?? null);
    if ($ctx === null) {
        return ['ok' => false, 'error' => '세션이 끝났습니다. 처음부터 다시 해 주십시오.'];
    }
    if (ip_blocked(client_ip()) !== null) {
        // 차단 전에 만든 대기 세션으로 문자를 계속 보내지 못하게.
        $_SESSION['otp_notice_' . $purpose] = ['ok' => false, 'error' => IPBLOCK_MESSAGE, 'tail' => '', 'dev_code' => ''];
        return ['ok' => false, 'error' => IPBLOCK_MESSAGE];
    }
    $owner = customer_owner_row($ctx);
    if ($owner === null || mobile_normalize((string)$owner['phone']) === null) {
        return ['ok' => false, 'error' => 'nophone'];
    }
    $res = otp_issue((int)$ctx['household_id'], (int)$owner['id'], $purpose, (string)$owner['phone']);
    $_SESSION['otp_notice_' . $purpose] = [
        'ok'       => $res['ok'],
        'error'    => $res['error'],
        'tail'     => $res['tail'] ?? substr(preg_replace('/\D/', '', (string)$owner['phone']), -4),
        'dev_code' => $res['dev_code'] ?? '',
    ];
    return $res;
}

/** 세션의 명의인 행 — 엑셀 재반입으로 owner.id 가 바뀌었으면 세대+이름으로 다시 찾는다. */
function customer_owner_row(array $ctx): ?array
{
    $row = db_row('SELECT id, name, phone FROM owner WHERE id = ? AND household_id = ?',
        [(int)($ctx['owner_id'] ?? 0), (int)$ctx['household_id']]);
    if ($row !== null) {
        return $row;
    }
    foreach (db_all('SELECT id, name, phone FROM owner WHERE household_id = ?', [(int)$ctx['household_id']]) as $o) {
        if (normalize_name($o['name']) === normalize_name((string)$ctx['owner_name'])) {
            return $o;
        }
    }
    return null;
}

/** 손님 세대 위치 (실패 집계용) */
function customer_ctx_place(array $ctx): array
{
    if (isset($ctx['complex_id'])) {
        return [(int)$ctx['complex_id'], (string)$ctx['dong'], (string)$ctx['ho']];
    }
    $h = db_row('SELECT complex_id, dong, ho FROM household WHERE id = ?', [(int)$ctx['household_id']]);
    return [(int)($h['complex_id'] ?? 0), (string)($h['dong'] ?? ''), (string)($h['ho'] ?? '')];
}

/**
 * 인증번호 확인 — login 이면 로그인 완료, reauth 면 재인증 시각 갱신.
 * @return array{ok:bool, error:string, state:string}
 */
function customer_verify_code(string $purpose, string $code): array
{
    if (ip_blocked(client_ip()) !== null) {
        return ['ok' => false, 'error' => IPBLOCK_MESSAGE, 'state' => 'ipblock'];
    }
    $ctx = $purpose === 'login' ? ($_SESSION['customer_pending'] ?? null) : ($_SESSION['customer'] ?? null);
    if ($ctx === null) {
        return ['ok' => false, 'error' => '세션이 끝났습니다. 처음부터 다시 해 주십시오.', 'state' => 'none'];
    }
    $owner = customer_owner_row($ctx);
    if ($owner === null) {
        return ['ok' => false, 'error' => '명의인 정보를 찾지 못했습니다. 1899-4252 로 연락해 주십시오.', 'state' => 'none'];
    }
    $res = otp_verify((int)$owner['id'], $purpose, $code);
    [$cx, $dong, $ho] = customer_ctx_place($ctx);
    if (!$res['ok']) {
        if ($res['state'] !== 'none') {
            auth_fail_record('sms_otp', $cx ?: null, $dong, $ho);
        }
        return $res;
    }
    auth_ok_record('sms_otp', $cx ?: null, $dong, $ho);
    unset($_SESSION['otp_notice_' . $purpose]);

    if ($purpose === 'login') {
        session_begin_role('customer');
        $_SESSION['customer'] = [
            'household_id' => (int)$ctx['household_id'],
            'owner_id'     => (int)$owner['id'],
            'owner_name'   => $ctx['owner_name'],
            'sms_at'       => time(),
        ];
        unset($_SESSION['customer_pending']);
        customer_log_action((int)$ctx['household_id'], (string)$ctx['owner_name'], 'login');
    } else {
        session_regenerate_id(true);
        $_SESSION['customer']['sms_at'] = time();
        customer_log_action((int)$ctx['household_id'], (string)$ctx['owner_name'], 'reauth');
    }
    return $res;
}

function customer_current(): ?array
{
    return $_SESSION['customer'] ?? null;
}

/** 손님 세션 필수 — 없으면 /login 으로 */
function customer_require(): array
{
    $c = customer_current();
    if ($c === null) {
        header('Location: /login');
        exit;
    }
    return $c;
}

/** 손님 작업기록 (주소/환불 저장 등) */
function customer_log_action(int $householdId, string $ownerName, string $action): void
{
    db_exec('INSERT INTO customer_log (household_id, owner_name, action, ip) VALUES (?,?,?,?)',
        [$householdId, $ownerName, $action, client_ip()]);
}
