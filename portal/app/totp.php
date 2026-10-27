<?php
/**
 * 직원 2단계 인증 — RFC 6238 TOTP (HMAC-SHA1, 6자리, 30초) 순수 PHP 구현.
 *  - 비밀키 20바이트, base32(RFC 4648) 로 사용자에게 보여주고 DB 에는 sodium 암호화 저장.
 *  - 검증: 현재 단계 ±1 허용. 통과한 단계는 staff_totp.last_step 에 남겨
 *    같은 코드(같은 단계 이하)를 다시 쓰지 못하게 한다.
 *  - 복구코드 8개: HMAC-SHA256 해시만 저장, 1회용.
 */

require_once __DIR__ . '/crypto.php';

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;
const TOTP_WINDOW = 1;
const TOTP_ISSUER = '제이엘 등기 관리자';
const RECOVERY_CODE_COUNT = 8;

function base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $ch) {
        $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alpha[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;                                        // 패딩(=)은 otpauth 에서 생략
}

function base32_decode(string $b32): ?string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[\s=\-]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $ch) {
        $v = strpos($alpha, $ch);
        if ($v === false) {
            return null;
        }
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

/** RFC 4226 HOTP — 카운터 8바이트 빅엔디언, 동적 절단 */
function hotp(string $secret, int $counter, int $digits = TOTP_DIGITS): string
{
    $msg = pack('J', $counter);
    $h = hash_hmac('sha1', $msg, $secret, true);
    $off = ord($h[19]) & 0x0F;
    $bin = ((ord($h[$off]) & 0x7F) << 24) | (ord($h[$off + 1]) << 16)
         | (ord($h[$off + 2]) << 8) | ord($h[$off + 3]);
    return str_pad((string)($bin % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

function totp_step(?int $time = null): int
{
    return intdiv($time ?? time(), TOTP_PERIOD);
}

function totp_code(string $secret, ?int $time = null): string
{
    return hotp($secret, totp_step($time));
}

/**
 * 코드 검증 — ±1 단계 안에서 맞고, 그 단계가 lastStep 보다 커야 통과.
 * @return int|null 통과한 단계 번호
 */
function totp_match(string $secret, string $code, int $lastStep, ?int $time = null): ?int
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== TOTP_DIGITS) {
        return null;
    }
    $now = totp_step($time);
    for ($s = $now - TOTP_WINDOW; $s <= $now + TOTP_WINDOW; $s++) {
        if ($s > $lastStep && hash_equals(hotp($secret, $s), $code)) {
            return $s;
        }
    }
    return null;
}

function totp_new_secret(): string
{
    return random_bytes(20);                            // 160비트 (RFC 4226 권장)
}

function totp_uri(string $secretB32, string $account): string
{
    $label = rawurlencode(TOTP_ISSUER) . ':' . rawurlencode($account);
    return 'otpauth://totp/' . $label . '?secret=' . $secretB32
        . '&issuer=' . rawurlencode(TOTP_ISSUER) . '&algorithm=SHA1&digits=' . TOTP_DIGITS
        . '&period=' . TOTP_PERIOD;
}

/* ---- DB ---------------------------------------------------------- */

function staff_totp_row(int $staffId): ?array
{
    return db_row('SELECT * FROM staff_totp WHERE staff_id = ?', [$staffId]);
}

function staff_totp_enabled(int $staffId): bool
{
    $r = staff_totp_row($staffId);
    return $r !== null && (int)$r['enabled'] === 1;
}

/** 등록 시작 — 새 비밀키를 (미확정 상태로) 저장하고 base32 를 돌려준다. */
function staff_totp_begin(int $staffId): string
{
    $secret = totp_new_secret();
    db_exec('INSERT INTO staff_totp (staff_id, secret_enc, enabled, last_step) VALUES (?,?,0,0)
             ON DUPLICATE KEY UPDATE secret_enc = VALUES(secret_enc), enabled = 0, last_step = 0, enabled_at = NULL',
        [$staffId, encrypt_field($secret)]);
    return base32_encode($secret);
}

function staff_totp_secret(array $row): ?string
{
    $s = decrypt_field((string)$row['secret_enc']);
    return ($s === null || $s === '') ? null : $s;
}

/**
 * 코드 검증 + 재사용 방지 갱신. 동시 요청에 대비해 조건부 UPDATE 로 단계를 선점한다.
 * $requireEnabled=false 는 등록 확인(enabled=0) 단계에서 쓴다.
 */
function staff_totp_verify(int $staffId, string $code, bool $requireEnabled = true): bool
{
    $row = staff_totp_row($staffId);
    if ($row === null || ($requireEnabled && (int)$row['enabled'] !== 1)) {
        return false;
    }
    $secret = staff_totp_secret($row);
    if ($secret === null) {
        return false;
    }
    $step = totp_match($secret, $code, (int)$row['last_step']);
    if ($step === null) {
        return false;
    }
    $won = db_exec('UPDATE staff_totp SET last_step = ? WHERE staff_id = ? AND last_step < ?',
        [$step, $staffId, $step]);
    return $won === 1;
}

function staff_totp_enable(int $staffId): void
{
    db_exec('UPDATE staff_totp SET enabled = 1, enabled_at = NOW() WHERE staff_id = ?', [$staffId]);
}

/** 관리자 초기화 — 비밀키·복구코드 삭제, 기존 세션 끊기 */
function staff_totp_reset(int $staffId): void
{
    db_exec('DELETE FROM staff_totp WHERE staff_id = ?', [$staffId]);
    db_exec('DELETE FROM staff_recovery_code WHERE staff_id = ?', [$staffId]);
    db_exec('UPDATE staff SET auth_version = auth_version + 1 WHERE id = ?', [$staffId]);
}

/* ---- 복구코드 ----------------------------------------------------- */

function recovery_hash(string $code): string
{
    $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    return hash_hmac('sha256', 'recovery|' . $norm, crypto_key());
}

/** 새 복구코드 8개 발급(기존 것은 폐기). 원문은 이때 한 번만 보여준다. */
function recovery_codes_issue(int $staffId): array
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';         // 헷갈리는 0/O/1/I 제외
    db_exec('DELETE FROM staff_recovery_code WHERE staff_id = ?', [$staffId]);
    $codes = [];
    for ($i = 0; $i < RECOVERY_CODE_COUNT; $i++) {
        $raw = '';
        for ($j = 0; $j < 10; $j++) {
            $raw .= $alpha[random_int(0, strlen($alpha) - 1)];
        }
        $code = substr($raw, 0, 5) . '-' . substr($raw, 5);
        db_exec('INSERT INTO staff_recovery_code (staff_id, code_hash) VALUES (?,?)', [$staffId, recovery_hash($code)]);
        $codes[] = $code;
    }
    return $codes;
}

/** 복구코드 사용 — 맞으면 즉시 사용 처리(1회용) */
function recovery_code_use(int $staffId, string $code): bool
{
    $n = db_exec('UPDATE staff_recovery_code SET used_at = NOW()
                   WHERE staff_id = ? AND code_hash = ? AND used_at IS NULL',
        [$staffId, recovery_hash($code)]);
    return $n === 1;
}

function recovery_codes_left(int $staffId): int
{
    return (int)(db_row('SELECT COUNT(*) AS n FROM staff_recovery_code WHERE staff_id = ? AND used_at IS NULL',
        [$staffId])['n'] ?? 0);
}

/* ---- 등록코드 (OTP 첫 등록·초기화 후 재등록용, admin 발급, 1회용·24시간) ---- */

const ENROLL_CODE_HOURS = 24;

/** 새 등록코드 발급 — 원문은 발급한 admin 화면(또는 CLI)에 한 번만 나온다. */
function staff_enroll_issue(int $staffId): string
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $raw = '';
    for ($i = 0; $i < 8; $i++) {
        $raw .= $alpha[random_int(0, strlen($alpha) - 1)];
    }
    $code = substr($raw, 0, 4) . '-' . substr($raw, 4);
    db_exec('UPDATE staff SET enroll_hash = ?, enroll_until = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?',
        [password_hash($code, PASSWORD_DEFAULT), ENROLL_CODE_HOURS, $staffId]);
    return $code;
}

function staff_enroll_valid(int $staffId): bool
{
    $r = db_row('SELECT enroll_hash IS NOT NULL AND enroll_until > NOW() AS ok FROM staff WHERE id = ?', [$staffId]);
    return (int)($r['ok'] ?? 0) === 1;
}

function staff_enroll_check(int $staffId, string $code): bool
{
    $r = db_row('SELECT enroll_hash FROM staff WHERE id = ? AND enroll_until > NOW()', [$staffId]);
    $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    $norm = strlen($norm) === 8 ? substr($norm, 0, 4) . '-' . substr($norm, 4) : $norm;
    return $r !== null && $r['enroll_hash'] !== null && password_verify($norm, (string)$r['enroll_hash']);
}

function staff_enroll_clear(int $staffId): void
{
    db_exec('UPDATE staff SET enroll_hash = NULL, enroll_until = NULL WHERE id = ?', [$staffId]);
}
