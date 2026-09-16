<?php
/* 조회 API 공통 — 설정 읽기, DB 연결, 응답 형식 */

declare(strict_types=1);

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

/** IP 도 개인정보다. 기록에는 해시만 남긴다. */
function ip_hash(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    // 프록시를 거치면 IP 가 여러 개 붙어 온다. 맨 앞이 실제 접속자다.
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return hash('sha256', cfg()['verify_salt'] . '|ip|' . $ip);
}

/** 본인확인 해시. 관리자 화면의 내보내기와 같은 방식이어야 한다. */
function verify_hash(string $name, string $birth): string
{
    return hash('sha256', cfg()['verify_salt'] . '|' . $name . '|' . $birth);
}
