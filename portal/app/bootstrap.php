<?php
/**
 * 부트스트랩 — 모든 요청이 public/index.php → 여기로 들어온다.
 * 세션 보안 설정은 session_start() 전에 끝내야 해서 이 파일이 제일 먼저다.
 */

declare(strict_types=1);

mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config.php';

$isDev = (portal_config()['env'] ?? 'prod') === 'dev';

// 개발에서는 에러를 바로 보고, 운영에서는 로그로만 남긴다.
ini_set('display_errors', $isDev ? '1' : '0');
error_reporting(E_ALL);

// 세션 강화 — 쿠키 탈취·CSRF 완화. secure 는 https 일 때만(로컬 http 대응).
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_name('JLPORTAL');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/survey.php';

// 공통 보안 헤더
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
