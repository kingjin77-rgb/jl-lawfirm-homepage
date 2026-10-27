<?php
/**
 * 문자 발송 드라이버 + 손님 문자 인증번호.
 *
 * 드라이버 (config 'sms_driver'):
 *   dev    — 실제 발송 없음. 인증번호를 화면에 보여주고 sms_log 에 기록. 운영(env=prod)이면 부팅 거부.
 *   aligo  — 알리고 HTTP API 스텁. config 'sms' => ['aligo' => ['key'=>, 'user_id'=>, 'sender'=>]]
 *   solapi — 솔라피 HTTP API 스텁. config 'sms' => ['solapi' => ['key'=>, 'secret'=>, 'sender'=>]]
 *   스텁은 요청 형식까지만 만들고 실제 호출은 하지 않는다 (업체 계약 후 sms_http_send 활성화).
 *
 * 인증번호: 6자리, HMAC-SHA256(+솔트) 해시만 저장, 3분 유효, 5회 오입력 시 폐기,
 * 재발송 60초 간격 · 시간당 5회.
 */

require_once __DIR__ . '/crypto.php';

const SMS_SENDER_NO      = '1899-4252';           // 사전등록 발신번호 (admin.php 의 SMS_SENDER 와 같은 값)
const OTP_TTL_SEC        = 180;
const OTP_MAX_ATTEMPTS   = 5;
const OTP_RESEND_SEC     = 60;
const OTP_HOURLY_LIMIT   = 5;

interface SmsDriver
{
    /** @return array{ok:bool, error:string, dev_code?:string} */
    public function send(string $to, string $body, array $meta = []): array;
    public function name(): string;
}

final class DevSmsDriver implements SmsDriver
{
    public function name(): string { return 'dev'; }

    public function send(string $to, string $body, array $meta = []): array
    {
        // 화면 표시는 호출부가 dev_code 로 한다. 실제 발송 없음.
        return ['ok' => true, 'error' => '', 'dev_code' => (string)($meta['code'] ?? '')];
    }
}

/** HTTP 문자 업체 공용 — 요청 형식만 만들고, 실제 전송은 enabled 일 때만. */
abstract class HttpSmsDriver implements SmsDriver
{
    public function __construct(protected array $conf) {}

    abstract protected function buildRequest(string $to, string $body): array; // [url, headers[], fields[]]

    public function send(string $to, string $body, array $meta = []): array
    {
        foreach ($this->requiredKeys() as $k) {
            if (trim((string)($this->conf[$k] ?? '')) === '') {
                return ['ok' => false, 'error' => $this->name() . " 설정 '$k' 가 비어 있습니다."];
            }
        }
        [$url, $headers, $fields] = $this->buildRequest($to, $body);
        if (empty($this->conf['live'])) {
            // 업체 계약·발신번호 등록 전 — 요청은 만들되 보내지 않는다.
            error_log('[jlportal] sms stub ' . $this->name() . ' → ' . $url . ' (live=false, 미발송)');
            return ['ok' => false, 'error' => '문자 업체 연동 전입니다 (live=false).'];
        }
        return sms_http_send($url, $headers, $fields);
    }

    abstract protected function requiredKeys(): array;
}

final class AligoSmsDriver extends HttpSmsDriver
{
    public function name(): string { return 'aligo'; }
    protected function requiredKeys(): array { return ['key', 'user_id', 'sender']; }

    protected function buildRequest(string $to, string $body): array
    {
        return ['https://apis.aligo.in/send/', [], [
            'key'      => $this->conf['key'],
            'user_id'  => $this->conf['user_id'],
            'sender'   => preg_replace('/\D/', '', (string)$this->conf['sender']),
            'receiver' => preg_replace('/\D/', '', $to),
            'msg'      => $body,
            'msg_type' => 'SMS',
        ]];
    }
}

final class SolapiSmsDriver extends HttpSmsDriver
{
    public function name(): string { return 'solapi'; }
    protected function requiredKeys(): array { return ['key', 'secret', 'sender']; }

    protected function buildRequest(string $to, string $body): array
    {
        // HMAC-SHA256 인증 헤더 (date + salt) — 솔라피 API v4 형식
        $date = gmdate('Y-m-d\TH:i:s\Z');
        $salt = bin2hex(random_bytes(16));
        $sig = hash_hmac('sha256', $date . $salt, (string)$this->conf['secret']);
        $auth = "HMAC-SHA256 apiKey={$this->conf['key']}, date=$date, salt=$salt, signature=$sig";
        return ['https://api.solapi.com/messages/v4/send', ['Authorization: ' . $auth, 'Content-Type: application/json'], [
            'message' => [
                'to'   => preg_replace('/\D/', '', $to),
                'from' => preg_replace('/\D/', '', (string)$this->conf['sender']),
                'text' => $body,
            ],
        ]];
    }
}

/** 실제 HTTP 전송 — live=true 일 때만 불린다. (업체 연동 시 응답 판정 보강) */
function sms_http_send(string $url, array $headers, array $fields): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl 확장이 없습니다.'];
    }
    $isJson = in_array('Content-Type: application/json', $headers, true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $isJson ? json_encode($fields, JSON_UNESCAPED_UNICODE) : http_build_query($fields),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $res !== false && $code >= 200 && $code < 300
        ? ['ok' => true, 'error' => '']
        : ['ok' => false, 'error' => '문자 업체 응답 오류 (HTTP ' . $code . ')'];
}

function sms_driver(): SmsDriver
{
    $c = portal_config();
    $name = (string)($c['sms_driver'] ?? 'dev');
    $conf = (array)($c['sms'][$name] ?? []);
    return match ($name) {
        'aligo'  => new AligoSmsDriver($conf),
        'solapi' => new SolapiSmsDriver($conf),
        default  => new DevSmsDriver(),
    };
}

/* ------------------------------------------------------------------ */
/* 휴대폰 번호                                                          */
/* ------------------------------------------------------------------ */

/** 휴대폰 정규화 — 01X 로 시작하는 10~11자리만 인정. 아니면 null. */
function mobile_normalize(string $raw): ?string
{
    $d = preg_replace('/\D/', '', $raw);
    if (str_starts_with($d, '82')) {
        $d = '0' . substr($d, 2);
    }
    return preg_match('/^01[016789]\d{7,8}$/', $d) ? $d : null;
}

/** 010-****-1234 */
function mask_phone(string $raw): string
{
    $d = preg_replace('/\D/', '', $raw);
    if ($d === '') {
        return '';
    }
    if (strlen($d) < 7) {
        return str_repeat('*', strlen($d));
    }
    return substr($d, 0, 3) . '-****-' . substr($d, -4);
}

/* ------------------------------------------------------------------ */
/* 인증번호                                                             */
/* ------------------------------------------------------------------ */

function otp_hash(string $code, string $salt): string
{
    return hash_hmac('sha256', 'sms-otp|' . $salt . '|' . $code, crypto_key());
}

/**
 * 인증번호 발송.
 * @return array{ok:bool, error:string, wait?:int, dev_code?:string, tail?:string}
 */
function otp_issue(int $householdId, int $ownerId, string $purpose, string $phone): array
{
    $mobile = mobile_normalize($phone);
    if ($mobile === null) {
        return ['ok' => false, 'error' => 'nophone'];
    }
    // 같은 명의인의 발송을 한 줄로 세운다 — 동시 요청이 60초·시간당 제한을 함께 통과하지 못하게.
    $lockName = 'jlportal-otp-' . $ownerId;
    if ((int)(db_row('SELECT GET_LOCK(?, 5) AS g', [$lockName])['g'] ?? 0) !== 1) {
        return ['ok' => false, 'error' => '잠시 뒤 다시 시도해 주십시오.'];
    }
    try {
        return otp_issue_locked($householdId, $ownerId, $purpose, $mobile);
    } finally {
        db_row('SELECT RELEASE_LOCK(?) AS r', [$lockName]);
    }
}

function otp_issue_locked(int $householdId, int $ownerId, string $purpose, string $mobile): array
{
    $last = db_row('SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS ago FROM otp_code
                     WHERE owner_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1', [$ownerId, $purpose]);
    if ($last !== null && (int)$last['ago'] < OTP_RESEND_SEC) {
        $wait = OTP_RESEND_SEC - (int)$last['ago'];
        return ['ok' => false, 'error' => "인증번호는 {$wait}초 뒤에 다시 받을 수 있습니다.", 'wait' => $wait];
    }
    $hour = (int)(db_row('SELECT COUNT(*) AS n FROM otp_code WHERE owner_id = ? AND purpose = ?
                            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$ownerId, $purpose])['n'] ?? 0);
    if ($hour >= OTP_HOURLY_LIMIT) {
        return ['ok' => false, 'error' => '인증번호 발송이 1시간 5회를 넘었습니다. 잠시 뒤 다시 시도하시거나 1899-4252 로 연락해 주십시오.'];
    }

    // 이전 번호는 전부 무효 — 가장 최근 번호 하나만 살아 있다.
    db_exec('UPDATE otp_code SET revoked = 1 WHERE owner_id = ? AND purpose = ? AND used_at IS NULL AND revoked = 0',
        [$ownerId, $purpose]);
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $salt = bin2hex(random_bytes(16));
    db_exec('INSERT INTO otp_code (household_id, owner_id, purpose, code_hash, salt, phone_tail, expires_at, ip)
             VALUES (?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)',
        [$householdId, $ownerId, $purpose, otp_hash($code, $salt), $salt, substr($mobile, -4), OTP_TTL_SEC, client_ip()]);

    $body = "[법무법인 제이엘] 인증번호 [$code] 를 입력해 주십시오. (3분 유효)";
    $driver = sms_driver();
    $res = $driver->send($mobile, $body, ['code' => $code, 'purpose' => $purpose]);
    // 기록에는 인증번호를 남기지 않는다.
    db_exec('INSERT INTO sms_log (staff_id, complex_id, msg_type, sender, body, target_cnt, status, purpose, to_masked, driver)
             VALUES (NULL, NULL, ?, ?, ?, 1, ?, ?, ?, ?)',
        ['SMS', SMS_SENDER_NO, str_replace($code, '******', $body), $res['ok'] ? 'sent' : 'failed',
         'otp.' . $purpose, mask_phone($mobile), $driver->name()]);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => '인증번호를 보내지 못했습니다. 1899-4252 로 연락해 주십시오.'];
    }
    $out = ['ok' => true, 'error' => '', 'tail' => substr($mobile, -4)];
    if ($driver->name() === 'dev' && portal_env() === 'dev') {
        $out['dev_code'] = $res['dev_code'] ?? '';
    }
    return $out;
}

/**
 * 인증번호 확인.
 * @return array{ok:bool, error:string, state:string}  state: ok|wrong|revoked|expired|none
 */
function otp_verify(int $ownerId, string $purpose, string $code): array
{
    $code = preg_replace('/\D/', '', $code);
    $row = db_row('SELECT id, code_hash, salt, attempts, revoked, used_at,
                          (expires_at < NOW()) AS expired
                     FROM otp_code WHERE owner_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1',
        [$ownerId, $purpose]);
    if ($row === null || $row['used_at'] !== null) {
        return ['ok' => false, 'state' => 'none', 'error' => '인증번호를 먼저 받아 주십시오.'];
    }
    if ((int)$row['revoked'] === 1) {
        return ['ok' => false, 'state' => 'revoked', 'error' => '인증번호가 폐기됐습니다. 새 인증번호를 받아 주십시오.'];
    }
    if ((int)$row['expired'] === 1) {
        return ['ok' => false, 'state' => 'expired', 'error' => '인증번호 유효시간(3분)이 지났습니다. 새 인증번호를 받아 주십시오.'];
    }
    // 시도 1회를 원자적으로 먼저 예약한다 — 동시에 여러 번 넣어도 5회를 넘길 수 없다.
    $reserved = db_exec('UPDATE otp_code SET attempts = attempts + 1
                          WHERE id = ? AND used_at IS NULL AND revoked = 0 AND attempts < ?',
        [(int)$row['id'], OTP_MAX_ATTEMPTS]);
    if ($reserved !== 1) {
        db_exec('UPDATE otp_code SET revoked = 1 WHERE id = ?', [(int)$row['id']]);
        return ['ok' => false, 'state' => 'revoked', 'error' => '인증번호를 5회 틀려 폐기했습니다. 새 인증번호를 받아 주십시오.'];
    }
    if (strlen($code) === 6 && hash_equals((string)$row['code_hash'], otp_hash($code, (string)$row['salt']))) {
        $n = db_exec('UPDATE otp_code SET used_at = NOW() WHERE id = ? AND used_at IS NULL AND revoked = 0', [(int)$row['id']]);
        return $n === 1
            ? ['ok' => true, 'state' => 'ok', 'error' => '']
            : ['ok' => false, 'state' => 'none', 'error' => '인증번호를 먼저 받아 주십시오.'];
    }
    // 폐기는 DB 값으로만 판단한다 (PHP 에서 계산한 값으로 revoked 를 되돌려 쓰지 않는다).
    db_exec('UPDATE otp_code SET revoked = 1 WHERE id = ? AND attempts >= ?', [(int)$row['id'], OTP_MAX_ATTEMPTS]);
    $now = db_row('SELECT attempts, revoked FROM otp_code WHERE id = ?', [(int)$row['id']]);
    if ((int)($now['revoked'] ?? 1) === 1) {
        return ['ok' => false, 'state' => 'revoked', 'error' => '인증번호를 5회 틀려 폐기했습니다. 새 인증번호를 받아 주십시오.'];
    }
    $left = OTP_MAX_ATTEMPTS - (int)$now['attempts'];
    return ['ok' => false, 'state' => 'wrong', 'error' => "인증번호가 맞지 않습니다. ({$left}회 남음)"];
}
