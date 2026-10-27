<?php
/**
 * 4단계 보안 공용 — 보안 헤더(CSP nonce) · 세션 강화(유휴/절대 만료, UA 지문) ·
 * 관리자 IP 허용목록 · IP 단위 차단 · 민감 작업 재인증 · 운영 설정 점검.
 * bootstrap.php 가 session_start() 전후로 부른다.
 */

require_once __DIR__ . '/config.php';

// 세션 만료 (초) — 손님 유휴 10분/절대 2시간, 직원 유휴 30분/절대 9시간.
const SESSION_IDLE_CUSTOMER = 600;
const SESSION_ABS_CUSTOMER  = 7200;
const SESSION_IDLE_STAFF    = 1800;
const SESSION_ABS_STAFF     = 32400;
const SESSION_PENDING_TTL   = 600;     // 1단계만 통과한 대기 상태 (문자/OTP 입력 전)

// 민감 작업 재인증 유효 시간 (초)
const REAUTH_CUSTOMER_SEC = 300;       // 손님: 최근 5분 내 문자 인증
const REAUTH_STAFF_SEC    = 600;       // 직원: 최근 10분 내 OTP

// IP 차단 — 10분 창에서 서로 다른 대상 5곳 이상 또는 30회 이상 실패 → 1시간
const IPBLOCK_WINDOW_MIN   = 10;
const IPBLOCK_DISTINCT     = 5;
const IPBLOCK_TOTAL        = 30;
const IPBLOCK_HOURS        = 1;

/**
 * 개발에서 쓴 crypto_key 의 SHA-256 지문 — 운영(env=prod)에서 같은 키면 부팅 거부.
 * 키 원문은 저장소에 두지 않는다. 개발 키를 새로 만들면 지문을 여기에 추가한다.
 */
const DEV_CRYPTO_KEY_FINGERPRINTS = [
    '3bb2e152f9da3216911dc1189c789e2cd7fc70ac9dc782eeb0ac3205ea75e6f0', // jl-portal-dev/config.local.php
];

function portal_env(): string
{
    return (portal_config()['env'] ?? 'prod') === 'dev' ? 'dev' : 'prod';
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    // 프록시 뒤라면 설정에서 명시적으로 신뢰할 때만 X-Forwarded-Proto 를 본다.
    if (!empty(portal_config()['trust_proxy_https'])
        && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

/** 시간 설정 — 개발(env=dev)에서만 config 'session_timeouts' 로 덮어쓸 수 있다 (E2E 시험용). */
function security_timeout(string $key, int $default): int
{
    if (portal_env() === 'dev') {
        $o = portal_config()['session_timeouts'][$key] ?? null;
        if (is_int($o) && $o > 0) {
            return $o;
        }
    }
    return $default;
}

/* ------------------------------------------------------------------ */
/* 운영 설정 점검 (항목 11)                                              */
/* ------------------------------------------------------------------ */

/** 운영 설정 문제 목록 — 비어 있으면 통과. tools/ 에서도 쓴다. */
function security_config_problems(array $c): array
{
    $p = [];
    if (($c['env'] ?? 'prod') === 'dev') {
        return $p;
    }
    $key = (string)($c['crypto_key'] ?? '');
    if (in_array(hash('sha256', $key), DEV_CRYPTO_KEY_FINGERPRINTS, true)) {
        $p[] = 'crypto_key 가 개발용 키와 같습니다. 운영 키를 새로 만드십시오.';
    }
    if ($key === '' || str_contains($key, '여기에')) {
        $p[] = 'crypto_key 가 비어 있거나 견본 값입니다.';
    }
    $driver = (string)($c['sms_driver'] ?? 'dev');
    if ($driver === 'dev') {
        $p[] = "운영에서 sms_driver 'dev'(화면에 인증번호 표시)는 쓸 수 없습니다.";
    } elseif (!in_array($driver, ['aligo', 'solapi'], true)) {
        $p[] = 'sms_driver 값이 올바르지 않습니다 (aligo | solapi).';
    }
    if (!empty($c['session_timeouts'])) {
        $p[] = 'session_timeouts 는 개발 전용입니다. 운영 설정에서 지우십시오.';
    }
    $cron = (string)($c['cron_token'] ?? '');
    if ($cron !== '' && strlen($cron) < 32) {
        $p[] = 'cron_token 은 32자 이상 무작위 값이어야 합니다.';
    }
    $bk = (string)($c['backup_key'] ?? '');
    if ($bk !== '' && hash_equals($bk, $key)) {
        $p[] = 'backup_key 를 crypto_key 와 다르게 두십시오.';
    }
    return $p;
}

/** 부팅 점검 — 운영이면 오류 표시 끄기, https 강제, 위험 설정이면 부팅 거부. */
function security_boot_checks(): void
{
    $c = portal_config();
    if (portal_env() !== 'prod') {
        return;
    }
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');

    $problems = security_config_problems($c);
    if ($problems !== []) {
        error_log('[jlportal] 운영 설정 거부: ' . implode(' / ', $problems));
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("운영 설정 점검에 실패해 서비스를 시작하지 않았습니다. 관리자는 서버 오류 로그를 확인해 주십시오.\n");
    }

    if (PHP_SAPI !== 'cli' && !is_https()) {
        $host = (string)($c['base_host'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
            http_response_code(400);
            exit('잘못된 요청입니다.');
        }
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/';
        }
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

/* ------------------------------------------------------------------ */
/* 보안 헤더 + CSP nonce (항목 7)                                        */
/* ------------------------------------------------------------------ */

function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
    return $nonce;
}

function security_headers(): void
{
    $n = csp_nonce();
    $font = 'https://cdn.jsdelivr.net';                  // Pretendard 웹폰트 CSS·woff2
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'nonce-$n'",
        // <style>·<link> 는 self + 폰트 CDN 만. style="" 속성만 허용한다(스크립트 실행 불가 영역).
        "style-src 'self' $font 'unsafe-inline'",        // style-src-elem 미지원 구형 브라우저 폴백
        "style-src-elem 'self' $font",
        "style-src-attr 'unsafe-inline'",
        "font-src 'self' $font data:",
        "img-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'none'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header_remove('X-Powered-By');
    header('Cross-Origin-Opener-Policy: same-origin');
    // 로그인 화면·개인정보 화면이 공용 PC 의 뒤로가기/캐시에 남지 않게.
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/* ------------------------------------------------------------------ */
/* 세션 강화 (항목 5)                                                   */
/* ------------------------------------------------------------------ */

function security_session_start(): void
{
    ini_set('session.use_strict_mode', '1');            // 모르는 세션ID 는 받지 않는다
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', '3600');          // 직원 유휴 30분보다 길게
    $savePath = (string)(portal_config()['session_path'] ?? '');
    if ($savePath !== '' && is_dir($savePath) && is_writable($savePath)) {
        session_save_path($savePath);                   // 공유호스팅: 웹루트 밖 전용 폴더 권장
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => is_https(),
    ]);
    session_name(is_https() ? '__Host-JLPORTAL' : 'JLPORTAL');
    session_start();
}

/** 역할별 만료 값 */
function session_limits(string $role): array
{
    return $role === 'staff'
        ? [security_timeout('staff_idle', SESSION_IDLE_STAFF), security_timeout('staff_abs', SESSION_ABS_STAFF)]
        : [security_timeout('customer_idle', SESSION_IDLE_CUSTOMER), security_timeout('customer_abs', SESSION_ABS_CUSTOMER)];
}

/** 로그인 직후 — 세션ID 재발급 + 역할 시계 시작 */
function session_begin_role(string $role): void
{
    session_regenerate_id(true);
    $_SESSION['_meta_' . $role] = ['created' => time(), 'last' => time()];
}

/** 역할 하나만 끝낸다 (손님·직원 세션은 서로 독립) */
function session_end_role(string $role): void
{
    unset($_SESSION[$role], $_SESSION['_meta_' . $role]);
    if ($role === 'customer') {
        unset($_SESSION['customer_pending']);
    } else {
        unset($_SESSION['staff_pending'], $_SESSION['staff_setup']);
    }
}

/**
 * 매 요청 — UA 지문이 바뀌면 세션 통째 폐기(탈취 쿠키 재사용 차단),
 * 역할별 유휴/절대 만료 검사, 대기 상태(1단계만 통과) 만료.
 */
function security_session_guard(): void
{
    $ua = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (isset($_SESSION['_ua']) && !hash_equals((string)$_SESSION['_ua'], $ua)) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_expired'] = 'ua';
    }
    $_SESSION['_ua'] = $ua;

    $now = time();
    foreach (['customer', 'staff'] as $role) {
        if (!isset($_SESSION[$role])) {
            continue;
        }
        $meta = $_SESSION['_meta_' . $role] ?? null;
        [$idle, $abs] = session_limits($role);
        if (!is_array($meta) || $now - (int)$meta['last'] > $idle || $now - (int)$meta['created'] > $abs) {
            session_end_role($role);
            session_regenerate_id(true);
            $_SESSION['_expired_' . $role] = 1;
            continue;
        }
        $_SESSION['_meta_' . $role]['last'] = $now;
    }
    foreach (['customer_pending', 'staff_pending', 'staff_setup'] as $k) {
        if (isset($_SESSION[$k]) && $now - (int)($_SESSION[$k]['at'] ?? 0) > SESSION_PENDING_TTL) {
            unset($_SESSION[$k]);
        }
    }
}

/** 만료 안내를 한 번만 꺼낸다 (로그인 화면이 띄운다). */
function session_take_expired(string $role): bool
{
    $hit = !empty($_SESSION['_expired_' . $role]) || (($_SESSION['_expired'] ?? '') === 'ua');
    unset($_SESSION['_expired_' . $role], $_SESSION['_expired']);
    return $hit;
}

/* ------------------------------------------------------------------ */
/* 관리자 IP 허용목록 (항목 3)                                          */
/* ------------------------------------------------------------------ */

/** "1.2.3.4" | "1.2.3.0/24" | IPv6 + /prefix */
function ip_in_cidr(string $ip, string $cidr): bool
{
    $ipBin = @inet_pton($ip);
    if ($ipBin === false) {
        return false;
    }
    $parts = explode('/', trim($cidr), 2);
    $netBin = @inet_pton($parts[0]);
    if ($netBin === false || strlen($netBin) !== strlen($ipBin)) {
        return false;
    }
    $max = strlen($ipBin) * 8;
    $bits = isset($parts[1]) ? (int)$parts[1] : $max;
    if ($bits < 0 || $bits > $max) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rem = $bits % 8;
    if ($rem === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rem)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}

/** 허용목록 텍스트 검증 — 줄마다 IP 또는 CIDR, # 뒤는 메모 */
function admin_allowlist_parse(string $text): array
{
    $entries = [];
    $bad = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim(explode('#', $line, 2)[0]);
        if ($line === '') {
            continue;
        }
        $parts = explode('/', $line, 2);
        $bin = @inet_pton($parts[0]);
        $ok = $bin !== false;
        if ($ok && isset($parts[1])) {
            $ok = ctype_digit($parts[1]) && (int)$parts[1] <= strlen($bin) * 8;
        }
        if ($ok) {
            $entries[] = $line;
        } else {
            $bad[] = $line;
        }
    }
    return ['entries' => array_values(array_unique($entries)), 'bad' => $bad];
}

/** 허용목록 — DB 를 못 읽으면 예외 (호출부가 거부로 처리: 실패 시 열리지 않게) */
function admin_allowlist(): array
{
    $row = db_row('SELECT value FROM setting WHERE name = ?', ['admin_ip_allowlist']);
    return admin_allowlist_parse((string)($row['value'] ?? ''))['entries'];
}

function admin_ip_allowed(string $ip, ?array $list = null): bool
{
    if ($list === null) {
        try {
            $list = admin_allowlist();
        } catch (Throwable $e) {
            error_log('[jlportal] 허용목록 읽기 실패 — 관리자 접근 거부: ' . $e->getMessage());
            return false;                               // 실패 시 닫힌다 (fail closed)
        }
    }
    if ($list === []) {
        return true;                                    // 비어 있으면 미적용
    }
    foreach ($list as $cidr) {
        if (ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------------ */
/* IP 단위 차단 (항목 4)                                                */
/* ------------------------------------------------------------------ */

/**
 * 차단·집계 단위 — IPv4 는 주소 그대로, IPv6 는 /64 대역 (한 가입자가 /64 를 통째로 쓰므로
 * 주소만 바꿔 가며 차단을 피하지 못하게).
 */
function ip_key(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin === false || strlen($bin) !== 16) {
        return $ip;
    }
    return inet_ntop(substr($bin, 0, 8) . str_repeat(" ", 8)) . '/64';
}

/** 차단 중이면 해제 시각, 아니면 null */
function ip_blocked(string $ip): ?string
{
    $ip = ip_key($ip);
    $row = db_row('SELECT blocked_until FROM ip_block
                    WHERE ip = ? AND blocked_until > NOW() AND released_by IS NULL
                    ORDER BY blocked_until DESC LIMIT 1', [$ip]);
    return $row['blocked_until'] ?? null;
}

/**
 * 직원 로그인용 차단 판정 — 허용목록이 설정돼 있고 그 안의 IP(사무실)면 자동 차단을 적용하지 않는다.
 * (같은 NAT 뒤 손님 실패로 사무실 직원이 1시간 못 들어오는 일을 막는다. 계정별 잠금은 그대로 적용.)
 */
function ip_blocked_for_staff(string $ip): ?string
{
    try {
        $list = admin_allowlist();
    } catch (Throwable $e) {
        $list = [];
    }
    if ($list !== [] && admin_ip_allowed($ip, $list)) {
        return null;
    }
    return ip_blocked($ip);
}

/**
 * 인증 실패 기록 + 차단 판정. 손님 로그인·문자 인증·직원 로그인·직원 OTP 공통.
 * 서로 다른 대상 = kind+단지+동+호+target 조합.
 */
function auth_fail_record(string $kind, ?int $complexId, string $dong, string $ho, string $target = ''): void
{
    $ip = ip_key(client_ip());
    db_exec('INSERT INTO login_log (kind, complex_id, dong, ho, target, ok, ip) VALUES (?,?,?,?,?,0,?)',
        [$kind, $complexId, mb_substr($dong, 0, 10), mb_substr($ho, 0, 10), mb_substr($target, 0, 60), $ip]);
    ip_block_evaluate($ip);
}

function auth_ok_record(string $kind, ?int $complexId, string $dong, string $ho, string $target = ''): void
{
    db_exec('INSERT INTO login_log (kind, complex_id, dong, ho, target, ok, ip) VALUES (?,?,?,?,?,1,?)',
        [$kind, $complexId, mb_substr($dong, 0, 10), mb_substr($ho, 0, 10), mb_substr($target, 0, 60), ip_key(client_ip())]);
}

function ip_block_evaluate(string $ip): bool
{
    $ip = ip_key($ip);
    if ($ip === '' || ip_blocked($ip) !== null) {
        return true;
    }
    $row = db_row(
        "SELECT COUNT(*) AS n,
                COUNT(DISTINCT CONCAT_WS('|', kind, IFNULL(complex_id, ''), dong, ho, target)) AS d
           FROM login_log
          WHERE ip = ? AND ok = 0 AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$ip, IPBLOCK_WINDOW_MIN]);
    $n = (int)($row['n'] ?? 0);
    $d = (int)($row['d'] ?? 0);
    if ($d >= IPBLOCK_DISTINCT || $n >= IPBLOCK_TOTAL) {
        db_exec('INSERT INTO ip_block (ip, reason, blocked_until) VALUES (?,?, DATE_ADD(NOW(), INTERVAL ? HOUR))',
            [$ip, "10분 내 실패 {$n}회 · 대상 {$d}곳", IPBLOCK_HOURS]);
        return true;
    }
    return false;
}

/* ------------------------------------------------------------------ */
/* 민감 작업 재인증 (항목 6)                                            */
/* ------------------------------------------------------------------ */

/** 앱 안 경로만 허용 — 외부 주소·스킴·개행이 섞인 next 는 기본값으로 */
function safe_next(string $next, string $prefix, string $default): string
{
    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $next) || !str_starts_with($next, $prefix)) {
        return $default;
    }
    return $next;
}

function customer_recent_sms(): bool
{
    $at = (int)($_SESSION['customer']['sms_at'] ?? 0);
    return $at > 0 && time() - $at <= security_timeout('reauth_customer', REAUTH_CUSTOMER_SEC);
}

function staff_recent_otp(): bool
{
    $at = (int)($_SESSION['staff']['otp_at'] ?? 0);
    return $at > 0 && time() - $at <= security_timeout('reauth_staff', REAUTH_STAFF_SEC);
}

/** 직원 민감 작업 앞 — 최근 10분 내 OTP 가 없으면 재확인 화면으로 */
function staff_require_reauth(?string $next = null): void
{
    if (staff_recent_otp()) {
        return;
    }
    $next = $next ?? (string)($_SERVER['REQUEST_URI'] ?? '/admin');
    redirect('/admin/reauth?next=' . rawurlencode(safe_next($next, '/admin', '/admin')));
}

/** 목록 화면의 「보기」 파라미터 — null(가림) | 'all' | 행 번호 */
function reveal_param(): string|int|null
{
    $r = (string)($_GET['reveal'] ?? '');
    if ($r === 'all') {
        return 'all';
    }
    return ctype_digit($r) && (int)$r > 0 ? (int)$r : null;
}

/** 보기 요청이면 재인증 + 열람 기록 */
function reveal_guard(array $staff, string $what): string|int|null
{
    $rev = reveal_param();
    if ($rev === null) {
        return null;
    }
    staff_require_reauth();
    staff_audit((int)$staff['id'], $what . '.reveal', $rev === 'all' ? 'page' : (string)$rev,
        '가린 정보 열람 (' . ($rev === 'all' ? '이 쪽 전체' : '행 ' . $rev) . ')');
    return $rev;
}

function reveal_hit(string|int|null $rev, int $id): bool
{
    return $rev === 'all' || $rev === $id;
}

/** 현재 경로 + 쿼리에 reveal 값만 바꾼 링크 */
function reveal_link(string|int $value): string
{
    $q = $_GET;
    $q['reveal'] = (string)$value;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return $path . '?' . http_build_query($q);
}
