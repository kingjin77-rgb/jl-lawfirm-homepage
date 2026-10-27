<?php
/**
 * 암호화 백업 복원 — tools/backup.php 가 만든 .jlbk 파일.
 *
 *   php portal/tools/restore.php <파일.jlbk> --decrypt-to <out.sql>   SQL 로만 풀기 (DB 안 건드림)
 *   php portal/tools/restore.php <파일.jlbk> --yes                    현재 설정의 DB 에 복원 (표를 지우고 다시 만든다!)
 *
 * 복원은 DROP TABLE → CREATE → INSERT 순서라 현재 DB 내용이 백업 시점으로 돌아간다.
 * 운영 DB 에 복원하기 전에 반드시 지금 상태를 한 번 더 백업해 둔다.
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI 에서만 실행합니다.');
}
require __DIR__ . '/../app/ops.php';

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "사용법: php restore.php <파일.jlbk> (--decrypt-to <out.sql> | --yes)\n");
    exit(2);
}
try {
    $i = array_search('--decrypt-to', $argv, true);
    if ($i !== false) {
        $out = $argv[$i + 1] ?? '';
        if ($out === '') {
            throw new RuntimeException('--decrypt-to 뒤에 출력 파일을 주십시오.');
        }
        $n = backup_decrypt_to($file, $out);
        echo "SQL 로 풀었습니다: $out ({$n}문)\n";
        exit(0);
    }
    if (!in_array('--yes', $argv, true)) {
        fwrite(STDERR, "DB 를 덮어씁니다. 확인했으면 --yes 를 붙여 다시 실행하십시오.\n");
        exit(2);
    }
    $r = backup_restore($file);
    echo "복원 완료: {$r['statements']}문 실행\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '복원 실패: ' . $e->getMessage() . "\n");
    exit(1);
}
