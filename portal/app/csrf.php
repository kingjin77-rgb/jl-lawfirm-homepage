<?php
/**
 * CSRF — 세션 토큰 방식. 모든 POST 폼은 csrf_field() 를 넣고,
 * 처리부는 맨 앞에서 csrf_verify_or_fail() 을 부른다.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** 폼 안에 넣는 hidden input */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** POST 토큰 검증 — 틀리면 403 으로 즉시 종료 */
function csrf_verify_or_fail(): void
{
    $sent = $_POST['_csrf'] ?? '';
    $mine = $_SESSION['csrf_token'] ?? '';
    if ($mine === '' || !is_string($sent) || !hash_equals($mine, $sent)) {
        http_response_code(403);
        exit('잘못된 요청입니다. 페이지를 새로고침한 뒤 다시 시도해 주십시오.');
    }
}
