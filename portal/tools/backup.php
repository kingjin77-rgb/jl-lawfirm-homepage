<?php
/**
 * DB 암호화 백업 — mysqldump 없이 PDO 로 전 표를 덤프해 sodium secretstream 으로 암호화.
 *
 *   php portal/tools/backup.php            config 'backup_dir' 에 jlportal_YYYYmmdd_His.jlbk 생성 + 최신 14개만 보관
 *   php portal/tools/backup.php <출력경로>  지정한 파일로
 *
 * 키: config 'backup_key' (32바이트 base64, crypto_key 와 다르게). 키를 잃으면 복원 불가 —
 * 설정 파일과 같은 곳이 아닌 별도 장소(대표 PC 금고 파일 등)에 사본을 둔다.
 * 쉘이 없는 가비아에서는 /cron/backup 웹크론 (X-Cron-Token 헤더) (app/cron.php).
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI 에서만 실행합니다.');
}
require __DIR__ . '/../app/ops.php';

try {
    $r = backup_run($argv[1] ?? null);
    $pruned = isset($argv[1]) ? 0 : backup_prune();
    echo "백업 완료: {$r['path']} (표 {$r['tables']}개 · 행 {$r['rows']}개 · " . number_format($r['bytes']) . " 바이트"
        . ($pruned ? " · 오래된 백업 {$pruned}개 정리" : '') . ")\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '백업 실패: ' . $e->getMessage() . "\n");
    exit(1);
}
