<?php
/**
 * 프런트 컨트롤러 — 모든 경로가 여기로 들어와 app/pages/ 의 페이지로 간다.
 * 로컬: php -S 127.0.0.1:8090 -t portal/public portal/public/index.php
 * 가비아: .htaccess 리라이트로 동일하게 동작 (README 참고).
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// php -S 내장서버의 라우터로 쓰일 때: 실제 존재하는 정적 파일은 그대로 서빙.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../app/bootstrap.php';

// 경로 → 페이지 스크립트. 여기 없는 경로는 전부 404.
$routes = [
    '/'         => 'home',
    '/login'    => 'login',
    '/logout'   => 'logout',
    '/home'     => 'home',
    '/progress' => 'progress',
    '/cost'     => 'cost',
    '/docs'     => 'docs',
    '/address'  => 'address',
    '/refund'   => 'refund',
    '/faq'      => 'faq',
];

$page = $routes[rtrim($path, '/') ?: '/'] ?? null;
if ($page === null) {
    http_response_code(404);
    exit('페이지를 찾을 수 없습니다.');
}

require __DIR__ . '/../app/pages/' . $page . '.php';
