<?php
/**
 * 인증 — 직원(아이디/비밀번호)과 손님(아파트+동+호+이름+생년월일6) 두 갈래.
 * 직원: 5회 실패 → 15분 잠금 (staff.failed_count / locked_until)
 * 손님: 세대(단지+동+호) 기준 1시간 10회 실패 → 잠금 (login_log 집계)
 */

require_once __DIR__ . '/db.php';

const STAFF_MAX_FAILS      = 5;
const STAFF_LOCK_MINUTES   = 15;
const CUSTOMER_MAX_FAILS   = 10;
const CUSTOMER_LOCK_WINDOW = 60; // 분

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
 * 직원 로그인 시도.
 * @return array{ok:bool, error:string, staff:?array}
 */
function staff_login(string $loginId, string $password): array
{
    $staff = db_row('SELECT * FROM staff WHERE login_id = ? AND is_active = 1', [$loginId]);

    if ($staff !== null && $staff['locked_until'] !== null
        && strtotime($staff['locked_until']) > time()) {
        return ['ok' => false, 'staff' => null,
            'error' => '로그인이 잠시 잠겼습니다. ' . STAFF_LOCK_MINUTES . '분 뒤 다시 시도해 주십시오.'];
    }

    // 계정이 없어도 비교 시간을 맞춰 계정 존재 여부가 새지 않게 한다.
    $hash = $staff['pass_hash'] ?? '';
    $ok = $hash !== '' && password_verify($password, $hash);

    if (!$ok) {
        if ($staff !== null) {
            $fails = (int)$staff['failed_count'] + 1;
            $lock = $fails >= STAFF_MAX_FAILS
                ? date('Y-m-d H:i:s', time() + STAFF_LOCK_MINUTES * 60) : null;
            db_exec('UPDATE staff SET failed_count = ?, locked_until = ? WHERE id = ?',
                [$fails >= STAFF_MAX_FAILS ? 0 : $fails, $lock, $staff['id']]);
        }
        return ['ok' => false, 'staff' => null, 'error' => '아이디 또는 비밀번호가 맞지 않습니다.'];
    }

    db_exec('UPDATE staff SET failed_count = 0, locked_until = NULL WHERE id = ?', [$staff['id']]);
    // 오래된 알고리즘 해시는 로그인 성공 시점에 상향
    if (password_needs_rehash($hash, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        db_exec('UPDATE staff SET pass_hash = ? WHERE id = ?', [staff_hash_password($password), $staff['id']]);
    }

    session_regenerate_id(true); // 세션 고정 공격 방지
    $_SESSION['staff'] = ['id' => (int)$staff['id'], 'name' => $staff['name'], 'role' => $staff['role']];
    staff_audit((int)$staff['id'], 'staff.login', 'staff:' . $staff['id']);
    return ['ok' => true, 'staff' => $staff, 'error' => ''];
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

/** 세대 잠금 여부 — 최근 1시간 실패 횟수로 판단 */
function customer_locked(int $complexId, string $dong, string $ho): bool
{
    $row = db_row(
        'SELECT COUNT(*) AS n FROM login_log
          WHERE complex_id = ? AND dong = ? AND ho = ? AND ok = 0
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
        [$complexId, $dong, $ho, CUSTOMER_LOCK_WINDOW]
    );
    return (int)($row['n'] ?? 0) >= CUSTOMER_MAX_FAILS;
}

/**
 * 손님 로그인 시도.
 * @return array{ok:bool, error:string}
 */
function customer_login(int $complexId, string $dong, string $ho, string $name, string $birth6): array
{
    $dong = trim($dong);
    $ho = trim($ho);
    $name = normalize_name($name);
    $birth6 = preg_replace('/\D/', '', $birth6);
    $ip = client_ip();

    if ($complexId <= 0 || $dong === '' || $ho === '' || $name === '' || strlen($birth6) !== 6) {
        return ['ok' => false, 'error' => '모든 항목을 채워 주십시오. 생년월일은 숫자 6자리입니다.'];
    }
    if (customer_locked($complexId, $dong, $ho)) {
        return ['ok' => false, 'error' => 'lockout'];
    }

    // 노출 중인 단지의 세대 → 소유자 이름+생년월일 대조 (공동명의는 둘 중 아무나)
    $owner = db_row(
        'SELECT o.id, o.name, o.household_id
           FROM household h
           JOIN complex c ON c.id = h.complex_id AND c.exposed = 1
           JOIN owner o ON o.household_id = h.id
          WHERE h.complex_id = ? AND h.dong = ? AND h.ho = ? AND o.birth6 = ?',
        [$complexId, $dong, $ho, $birth6]
    );
    // 이름은 정규화해서 PHP 쪽에서 대조한다 (DB 콜레이션의 공백 처리에 기대지 않는다).
    $matched = $owner !== null && normalize_name($owner['name']) === $name;
    if (!$matched && $owner !== null) {
        // 생년월일이 같은 다른 명의인이 있을 수 있으니 세대의 전 명의인을 훑는다.
        $owners = db_all(
            'SELECT o.id, o.name, o.household_id FROM owner o
              WHERE o.household_id = ? AND o.birth6 = ?',
            [$owner['household_id'], $birth6]
        );
        foreach ($owners as $cand) {
            if (normalize_name($cand['name']) === $name) {
                $owner = $cand;
                $matched = true;
                break;
            }
        }
    }

    db_exec('INSERT INTO login_log (complex_id, dong, ho, ok, ip) VALUES (?,?,?,?,?)',
        [$complexId, $dong, $ho, $matched ? 1 : 0, $ip]);

    if (!$matched) {
        if (customer_locked($complexId, $dong, $ho)) {
            return ['ok' => false, 'error' => 'lockout'];
        }
        return ['ok' => false, 'error' => '입력하신 내용과 일치하는 세대가 없습니다. 계약서에 적힌 분의 성함과 생년월일인지 확인해 주십시오.'];
    }

    session_regenerate_id(true);
    $_SESSION['customer'] = [
        'household_id' => (int)$owner['household_id'],
        'owner_name'   => $owner['name'],
    ];
    db_exec('INSERT INTO customer_log (household_id, owner_name, action, ip) VALUES (?,?,?,?)',
        [(int)$owner['household_id'], $owner['name'], 'login', $ip]);
    return ['ok' => true, 'error' => ''];
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
