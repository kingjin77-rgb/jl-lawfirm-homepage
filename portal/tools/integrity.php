<?php
/**
 * 파일 무결성 점검 (웹쉘·변조 탐지) — CLI 또는 크론.
 *
 *   php portal/tools/integrity.php baseline   배포 직후 기준선 생성 (코드를 바꿔 올릴 때마다 다시)
 *   php portal/tools/integrity.php check      기준선과 대조 — 다르면 종료코드 1 (크론 메일 알림용)
 *   php portal/tools/integrity.php check --json
 *
 * 쉘이 없는 가비아에서는 /cron/integrity 웹크론(X-Cron-Token 헤더)으로 check 만 돌린다 (app/cron.php).
 * 기준선 위치: config 'integrity_baseline' (기본 portal/integrity-baseline.json — 웹루트 밖).
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI 에서만 실행합니다.');
}
require __DIR__ . '/../app/ops.php';

$cmd = $argv[1] ?? '';
try {
    if ($cmd === 'baseline') {
        $r = integrity_make_baseline();
        echo "기준선 생성: {$r['count']}개 파일 → {$r['path']}\n";
        exit(0);
    }
    if ($cmd === 'check') {
        $r = integrity_check();
        echo in_array('--json', $argv, true)
            ? json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            : integrity_report_text($r);
        exit($r['ok'] ? 0 : 1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, '오류: ' . $e->getMessage() . "\n");
    exit(2);
}
fwrite(STDERR, "사용법: php integrity.php baseline | check [--json]\n");
exit(2);
