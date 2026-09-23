<?php
/* 조회 API 공통 — 설정 읽기, DB 연결, 응답 형식 */

declare(strict_types=1);

/* 오류 내용이 화면에 찍히면 경로·쿼리·접속 정보가 그대로 새어 나간다.
 * 호스팅 기본값이 켜져 있어도 여기서 끈다. 자세한 내용은 서버 로그에만 남긴다. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    error_log('[track] ' . get_class($e) . ': ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine());
    json_out([
        'ok'      => false,
        'error'   => 'server',
        'message' => '지금 조회가 되지 않습니다. 잠시 뒤 다시 시도해 주십시오. 문의 1899-4252',
    ], 500);
});

function cfg(): array
{
    static $c = null;
    if ($c === null) {
        $path = __DIR__ . '/../config.php';
        if (!is_file($path)) {
            json_out(['ok' => false, 'message' => '서버 설정이 아직 끝나지 않았습니다.'], 500);
        }
        $c = require $path;
    }
    return $c;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $d = cfg()['db'];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $d['host'], $d['name'], $d['charset']);
        try {
            $pdo = new PDO($dsn, $d['user'], $d['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (Throwable $e) {
            // 접속 정보가 오류 화면에 찍히면 그대로 유출이다. 자세한 내용은 내보내지 않는다.
            error_log('[track] db connect: ' . $e->getMessage());
            json_out(['ok' => false, 'message' => '지금 조회가 되지 않습니다. 잠시 뒤 다시 시도해 주십시오.'], 500);
        }
    }
    return $pdo;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 홈페이지 주소에서 온 요청만 받는다. */
function cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, cfg()['allow_origins'], true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Upload-Token');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function body_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/** IP 도 개인정보다. 기록에는 해시만 남긴다.
 *
 * X-Forwarded-For 는 보내는 쪽이 마음대로 적을 수 있다.
 * 그 값을 믿으면 요청마다 IP 를 바꿔 적어 두드림 제한을 그냥 넘는다.
 * 가비아 웹호스팅은 앞단 프록시 없이 바로 받으므로 REMOTE_ADDR 만 본다. */
function ip_hash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return hash('sha256', salt() . '|ip|' . $ip);
}

/** 해시에 섞는 문구. 관리자 화면이 앞뒤 공백을 잘라 쓰므로 여기서도 자른다. */
function salt(): string
{
    return trim((string)cfg()['verify_salt']);
}

/** 이름 맞춤. 관리자 화면(office/core.js)·tracking.js 의 norm() 과 같아야 한다.
 *  NFC 로 모으고, 공백은 모두 빼고, 영문 소문자만 대문자로 바꾼다.
 *  intl 확장이 없는 호스팅이면 NFC 는 건너뛴다. 조회 화면이 이미 맞춰서 보낸다. */
function norm_name(string $name): string
{
    if (class_exists('Normalizer')) {
        $n = Normalizer::normalize($name, Normalizer::FORM_C);
        if (is_string($n)) $name = $n;
    }
    // 자바스크립트 \s 와 같은 범위: ASCII 공백 + 유니코드 공백(\p{Z}) + BOM
    $name = (string)preg_replace('/[\s\p{Z}\x{FEFF}]+/u', '', $name);
    return strtr($name, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
}

/** 본인확인 해시. 관리자 화면의 내보내기와 같은 방식이어야 한다.
 *  sha256( salt | norm(이름) | 생년월일 6자리 ) */
function verify_hash(string $name, string $birth): string
{
    return hash('sha256', salt() . '|' . norm_name($name) . '|' . $birth);
}

/** 조회 기록은 두드림 제한에만 쓴다. 30일 지난 것은 지운다.
 *  매번 지우면 느려지므로 100번에 한 번꼴로만 돈다. */
function prune_log(): void
{
    if (mt_rand(1, 100) !== 1) return;
    try {
        db()->exec('DELETE FROM lookup_log WHERE at < (NOW() - INTERVAL 30 DAY)');
    } catch (Throwable $e) {
        error_log('[track] prune: ' . $e->getMessage());
    }
}
