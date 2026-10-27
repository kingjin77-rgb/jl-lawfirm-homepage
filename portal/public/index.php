<?php
/**
 * 프런트 컨트롤러 — 모든 경로가 여기로 들어와 app/pages/ 의 페이지로 간다.
 * 로컬: php -S 127.0.0.1:8090 -t portal/public portal/public/index.php
 * 가비아: .htaccess 리라이트로 동일하게 동작 (README 참고).
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// php -S 내장서버의 라우터로 쓰일 때: 실제 존재하는 정적 파일은 그대로 서빙.
// 운영(Apache)의 .htaccess 규칙과 같게: index.php 외 .php 실행 금지, 숨김파일·.sql·.md 차단.
if (PHP_SAPI === 'cli-server') {
    $base = basename($path);
    if (($base !== '' && $base[0] === '.') || preg_match('/\.(php\d?|phtml|phar|inc|sql|md|bak|log|ini|env|sh)$/i', $path)) {
        http_response_code(404);
        exit('페이지를 찾을 수 없습니다.');
    }
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
    '/login/verify' => 'login_verify',
    '/verify'   => 'reauth',
    '/logout'   => 'logout',
    '/home'     => 'home',
    '/progress' => 'progress',
    '/cost'     => 'cost',
    '/docs'     => 'docs',
    '/address'  => 'address',
    '/refund'   => 'refund',
    '/faq'      => 'faq',
    '/participate' => 'survey_join',
];

// 직원 관리자 — /admin 아래는 별도 라우트 표 (app/pages/admin/*.php).
$adminRoutes = [
    '/admin'                      => 'dashboard',
    '/admin/login'                => 'login',
    '/admin/otp'                  => 'otp',
    '/admin/otp/setup'            => 'otp_setup',
    '/admin/otp/recovery'         => 'otp_recovery',
    '/admin/reauth'               => 'reauth',
    '/admin/config/security'      => 'config_security',
    '/admin/logout'               => 'logout',
    '/admin/config'               => 'config_info',
    '/admin/config/ops'           => 'config_ops',
    '/admin/config/popup'         => 'config_popup',
    '/admin/config/faq'           => 'config_faq',
    '/admin/config/staff'         => 'config_staff',
    '/admin/member/complex'       => 'member_complex',
    '/admin/member/household'     => 'member_household',
    '/admin/member/sms'           => 'member_sms',
    '/admin/member/sms-log'       => 'member_sms_log',
    '/admin/registration'         => 'reg_list',
    '/admin/registration/form'    => 'reg_form',
    '/admin/registration/cost'    => 'reg_cost',
    '/admin/registration/address' => 'reg_address',
    '/admin/registration/refund'  => 'reg_refund',
    '/admin/registration/upload'  => 'reg_upload',
    '/admin/registration/export'  => 'reg_export',
    // 설문·등기접수·위임장 — 세 메뉴가 같은 컨트롤러(survey_*)를 쓴다.
    // $surveyType 은 아래에서 경로 접두로 정한다.
    '/admin/survey'            => 'survey_list',
    '/admin/survey/form'       => 'survey_form',
    '/admin/survey/preview'    => 'survey_preview',
    '/admin/survey/result'     => 'survey_result',
    '/admin/survey/data'       => 'survey_data',
    '/admin/survey/target'     => 'survey_target',
    '/admin/survey/sms'        => 'survey_sms',
    '/admin/accept'            => 'survey_list',
    '/admin/accept/form'       => 'survey_form',
    '/admin/accept/preview'    => 'survey_preview',
    '/admin/accept/result'     => 'survey_result',
    '/admin/accept/data'       => 'survey_data',
    '/admin/accept/target'     => 'survey_target',
    '/admin/accept/sms'        => 'survey_sms',
    '/admin/attorney'          => 'survey_list',
    '/admin/attorney/form'     => 'survey_form',
    '/admin/attorney/preview'  => 'survey_preview',
    '/admin/attorney/result'   => 'survey_result',
    '/admin/attorney/data'     => 'survey_data',
    '/admin/attorney/target'   => 'survey_target',
    '/admin/attorney/sms'      => 'survey_sms',
    '/admin/stats'                => 'stats_login',
    '/admin/stats/lockout'        => 'stats_lockout',
    '/admin/stats/audit'          => 'stats_audit',
];

$clean = rtrim($path, '/') ?: '/';

// 운영 도구 웹크론 — 쉘 없는 가비아용. config 'cron_token' 이 비어 있으면 404.
if (str_starts_with($clean, '/cron/')) {
    require __DIR__ . '/../app/cron.php';
    exit;
}

if ($clean === '/admin' || str_starts_with($clean, '/admin/')) {
    // 관리자 IP 허용목록 — 비어 있으면 미적용. 목록 밖 IP 는 로그인 화면조차 열지 않는다.
    if (!admin_ip_allowed(client_ip())) {
        http_response_code(403);
        exit('허용되지 않은 접속 위치입니다.');
    }
    require __DIR__ . '/../app/admin.php';
    // 설문 엔진 공용 컨트롤러의 유형 — 경로 접두로 정한다.
    $surveyType = 'survey';
    if (str_starts_with($clean, '/admin/accept')) {
        $surveyType = 'accept';
    } elseif (str_starts_with($clean, '/admin/attorney')) {
        $surveyType = 'attorney';
    }
    $page = $adminRoutes[$clean] ?? null;
    if ($page === null) {
        http_response_code(404);
        exit('페이지를 찾을 수 없습니다.');
    }
    require __DIR__ . '/../app/pages/admin/' . $page . '.php';
    exit;
}

$page = $routes[$clean] ?? null;
if ($page === null) {
    http_response_code(404);
    exit('페이지를 찾을 수 없습니다.');
}

require __DIR__ . '/../app/pages/' . $page . '.php';
