<?php
/**
 * 부트스트랩 — 모든 요청이 public/index.php → 여기로 들어온다.
 * 순서: 운영 설정 점검(https 강제·개발키/개발 문자드라이버 거부) → 세션 시작(강화) →
 *       세션 가드(유휴/절대 만료·UA 지문) → 보안 헤더(CSP nonce).
 */

declare(strict_types=1);

mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

$isDev = portal_env() === 'dev';

// 개발에서는 에러를 바로 보고, 운영에서는 로그로만 남긴다.
ini_set('display_errors', $isDev ? '1' : '0');
error_reporting(E_ALL);

security_boot_checks();          // 운영: https 아니면 리다이렉트, 위험 설정이면 503
security_session_start();        // HttpOnly · SameSite=Strict · Secure(https) · strict mode

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/survey.php';

security_session_guard();        // 유휴 손님 10분/직원 30분, 절대 2시간/9시간, UA 바뀌면 폐기
security_headers();              // CSP(nonce) · HSTS(https) · nosniff · Referrer · Permissions · XFO
