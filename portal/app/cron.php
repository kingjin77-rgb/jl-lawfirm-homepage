<?php
/**
 * 웹크론 엔드포인트 — 쉘(SSH)이 없는 가비아 공유호스팅용.
 *   GET /cron/integrity   파일 무결성 대조 (기준선 생성은 CLI 로만 — 웹으로 기준선을 바꿀 수 없게)
 *   GET /cron/backup      DB 암호화 백업 + 오래된 것 정리
 * 토큰: 헤더 X-Cron-Token (config 'cron_token', 32자 이상). 비어 있으면 404.
 *       ?token= 은 config 'cron_allow_query' => true 일 때만 (접속 로그에 토큰이 남는다).
 * 이상이 있으면 HTTP 500 을 돌려 크론 서비스의 실패 알림(메일)이 나가게 한다.
 */

require_once __DIR__ . '/ops.php';

$cfgToken = (string)(portal_config()['cron_token'] ?? '');
// 기본은 헤더(X-Cron-Token)만 받는다. URL 의 ?token= 은 접속 로그에 남으므로
// 크론 서비스가 헤더를 못 붙일 때만 config 'cron_allow_query' => true 로 연다.
$given = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
if ($given === '' && !empty(portal_config()['cron_allow_query'])) {
    $given = (string)($_GET['token'] ?? '');
}
header('Content-Type: text/plain; charset=UTF-8');
if ($cfgToken === '' || strlen($cfgToken) < 32) {
    http_response_code(404);
    exit("not found\n");
}
if ($given === '' || !hash_equals($cfgToken, $given)) {
    auth_fail_record('cron', null, '', '', 'cron');
    http_response_code(404);
    exit("not found\n");
}

$job = $clean === '/cron/integrity' ? 'integrity' : ($clean === '/cron/backup' ? 'backup' : '');
@set_time_limit(300);
try {
    if ($job === 'integrity') {
        $r = integrity_check();
        if (!$r['ok']) {
            error_log('[jlportal] ' . trim(integrity_report_text($r)));
            staff_audit(null, 'cron.integrity.alert', 'files', mb_substr(integrity_report_text($r), 0, 2000));
            http_response_code(500);
        }
        echo integrity_report_text($r);
    } elseif ($job === 'backup') {
        $r = backup_run();
        $pruned = backup_prune();
        staff_audit(null, 'cron.backup', 'db', "표 {$r['tables']} · 행 {$r['rows']} · " . basename($r['path']));
        echo "백업 완료: " . basename($r['path']) . " (행 {$r['rows']}, 정리 {$pruned})\n";
    } else {
        http_response_code(404);
        echo "not found\n";
    }
} catch (Throwable $e) {
    error_log('[jlportal] cron ' . $job . ' 실패: ' . $e->getMessage());
    http_response_code(500);
    echo "실패: 서버 오류 로그를 확인하십시오.\n";
}
