<?php
/**
 * 운영 도구 공용 — tools/integrity.php · tools/backup.php · tools/restore.php · /cron/* 가 쓴다.
 *
 * 무결성: public/ · app/ 의 파일 SHA-256 기준선(JSON)을 만들고 대조한다(웹쉘·변조 탐지).
 *         기준선은 HMAC(crypto_key 파생)으로 서명해, 공격자가 기준선까지 고쳐 쓰면 「서명 불일치」로 잡힌다.
 * 백업:   mysqldump 없이 PDO 로 전 표를 덤프(DROP/CREATE + INSERT) → 조각별 gzip → sodium secretstream
 *         (XChaCha20-Poly1305) 암호화 파일. 키는 config 'backup_key'(32바이트 base64, crypto_key 와 다르게).
 * 복원:   같은 키로 풀어 순서대로 실행 (또는 SQL 파일로만 풀기).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';

const BACKUP_MAGIC      = "JLBK1\n";
const BACKUP_ROWS_CHUNK = 2000;
const BACKUP_KEEP       = 14;

/* ------------------------------------------------------------------ */
/* 무결성                                                               */
/* ------------------------------------------------------------------ */

function ops_portal_root(): string
{
    return dirname(__DIR__);
}

function integrity_baseline_path(): string
{
    $p = (string)(portal_config()['integrity_baseline'] ?? '');
    return $p !== '' ? $p : ops_portal_root() . DIRECTORY_SEPARATOR . 'integrity-baseline.json';
}

/** 감시 대상 — 웹에 노출되는 public/ 과 실행 코드 app/ */
function integrity_scan(): array
{
    $root = ops_portal_root();
    $out = [];
    foreach (['public', 'app'] as $top) {
        $base = $root . DIRECTORY_SEPARATOR . $top;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $rel = $top . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
            $out[$rel] = hash_file('sha256', $f->getPathname());
        }
    }
    ksort($out);
    return $out;
}

function integrity_sign(array $files): string
{
    $k = sodium_crypto_generichash('jlportal-integrity', crypto_key(), 32);
    return hash_hmac('sha256', json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $k);
}

function integrity_make_baseline(): array
{
    $files = integrity_scan();
    $doc = ['created_at' => date('c'), 'count' => count($files), 'files' => $files, 'sig' => integrity_sign($files)];
    $path = integrity_baseline_path();
    if (file_put_contents($path, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        throw new RuntimeException('기준선 파일을 쓸 수 없습니다: ' . $path);
    }
    return ['path' => $path, 'count' => count($files)];
}

/**
 * @return array{ok:bool, error:string, added:array, removed:array, changed:array, suspicious:array}
 */
function integrity_check(): array
{
    $res = ['ok' => false, 'error' => '', 'added' => [], 'removed' => [], 'changed' => [], 'suspicious' => []];
    $path = integrity_baseline_path();
    $doc = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
    if (!is_array($doc) || !isset($doc['files'], $doc['sig'])) {
        $res['error'] = '기준선이 없습니다. 먼저 tools/integrity.php baseline 을 실행하십시오.';
        return $res;
    }
    if (!hash_equals(integrity_sign($doc['files']), (string)$doc['sig'])) {
        $res['error'] = '기준선 서명이 맞지 않습니다 — 기준선 파일이 변조됐을 수 있습니다.';
        return $res;
    }
    $now = integrity_scan();
    $base = $doc['files'];
    $res['added'] = array_values(array_diff(array_keys($now), array_keys($base)));
    $res['removed'] = array_values(array_diff(array_keys($base), array_keys($now)));
    foreach ($now as $f => $h) {
        if (isset($base[$f]) && !hash_equals($base[$f], $h)) {
            $res['changed'][] = $f;
        }
    }
    // 웹루트의 새 PHP 파일·숨김파일은 웹쉘 의심으로 따로 표시
    foreach ($res['added'] as $f) {
        if (preg_match('#^public/.*(\.(php\d?|phtml|phar|inc)$|/\.[^/]+$)#i', $f)) {
            $res['suspicious'][] = $f;
        }
    }
    $res['ok'] = $res['added'] === [] && $res['removed'] === [] && $res['changed'] === [];
    return $res;
}

function integrity_report_text(array $r): string
{
    if ($r['error'] !== '') {
        return "[무결성] 실패: {$r['error']}\n";
    }
    if ($r['ok']) {
        return "[무결성] 이상 없음 — 기준선과 같습니다.\n";
    }
    $t = "[무결성] 변경 감지!\n";
    foreach (['suspicious' => '웹쉘 의심', 'added' => '추가', 'removed' => '삭제', 'changed' => '변경'] as $k => $label) {
        foreach ($r[$k] as $f) {
            $t .= "  $label: $f\n";
        }
    }
    return $t;
}

/* ------------------------------------------------------------------ */
/* 백업 / 복원                                                          */
/* ------------------------------------------------------------------ */

function backup_key(): string
{
    $raw = base64_decode((string)(portal_config()['backup_key'] ?? ''), true);
    if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
        throw new RuntimeException("config 'backup_key' 가 없거나 32바이트 base64 가 아닙니다.");
    }
    if (hash_equals($raw, crypto_key())) {
        throw new RuntimeException('backup_key 를 crypto_key 와 다르게 두십시오.');
    }
    return $raw;
}

function backup_dir(): string
{
    $d = (string)(portal_config()['backup_dir'] ?? '');
    if ($d === '') {
        throw new RuntimeException("config 'backup_dir' (웹루트 밖 폴더)를 지정하십시오.");
    }
    if (!is_dir($d) && !@mkdir($d, 0700, true) && !is_dir($d)) {
        throw new RuntimeException('백업 폴더를 만들 수 없습니다: ' . $d);
    }
    $real = (string)realpath($d);
    $pub = realpath(ops_portal_root() . '/public');
    if ($pub !== false && str_starts_with(strtolower($real) . DIRECTORY_SEPARATOR, strtolower($pub) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('백업 폴더가 웹루트 안에 있습니다.');
    }
    return $real;
}

/** 표 이름 — 백틱 이스케이프 (SHOW TABLES 결과만 들어온다) */
function ops_ident(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/** @return array{path:string, tables:int, rows:int, bytes:int} */
function backup_run(?string $outPath = null): array
{
    $key = backup_key();
    $pdo = db();
    $path = $outPath ?? (backup_dir() . DIRECTORY_SEPARATOR . 'jlportal_' . date('Ymd_His') . '.jlbk');
    $fh = fopen($path . '.part', 'wb');
    if ($fh === false) {
        throw new RuntimeException('백업 파일을 쓸 수 없습니다: ' . $path);
    }
    [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
    fwrite($fh, BACKUP_MAGIC . $header);
    $push = function (array $stmts, bool $final) use ($fh, &$state): void {
        $plain = gzcompress(json_encode($stmts, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 6);
        $c = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, '',
            $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                   : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
        fwrite($fh, pack('N', strlen($c)) . $c);
    };

    $tables = array_map(fn($r) => array_values($r)[0], db_all('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\''));
    // 한 시점의 일관된 사진 — 덤프 도중 손님·직원이 써도 표끼리 어긋나지 않게 (InnoDB 스냅숏).
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    $push(['-- jlportal backup ' . date('c') . ' tables=' . count($tables), 'SET NAMES utf8mb4', 'SET FOREIGN_KEY_CHECKS=0'], false);
    $rowsTotal = 0;
    foreach ($tables as $t) {
        $create = array_values(db_row('SHOW CREATE TABLE ' . ops_ident($t)))[1];
        $push(['DROP TABLE IF EXISTS ' . ops_ident($t), $create], false);
        $cols = null;
        // 기본키 순서로 고정해 쪽 나눔 중 행이 빠지거나 겹치지 않게 (스냅숏 안이라 OFFSET 도 안정).
        $pk = array_map(fn($k) => ops_ident((string)$k['Column_name']),
            db_all('SHOW KEYS FROM ' . ops_ident($t) . " WHERE Key_name = 'PRIMARY'"));
        $order = $pk !== [] ? ' ORDER BY ' . implode(',', $pk) : '';
        $expect = (int)(db_row('SELECT COUNT(*) AS n FROM ' . ops_ident($t))['n'] ?? 0);
        $got = 0;
        for ($off = 0; ; $off += BACKUP_ROWS_CHUNK) {
            $rows = db_all('SELECT * FROM ' . ops_ident($t) . $order . ' LIMIT ' . BACKUP_ROWS_CHUNK . ' OFFSET ' . $off);
            if ($rows === []) {
                break;
            }
            $cols ??= '(' . implode(',', array_map('ops_ident', array_keys($rows[0]))) . ')';
            $vals = [];
            foreach ($rows as $r) {
                $vals[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r)) . ')';
            }
            $push(['INSERT INTO ' . ops_ident($t) . ' ' . $cols . ' VALUES ' . implode(',', $vals)], false);
            $rowsTotal += count($rows);
            $got += count($rows);
            if (count($rows) < BACKUP_ROWS_CHUNK) {
                break;
            }
        }
        if ($got !== $expect) {
            fclose($fh);
            @unlink($path . '.part');
            $pdo->exec('ROLLBACK');
            throw new RuntimeException("표 {$t} 행 수 불일치 (기대 {$expect} / 덤프 {$got}) — 백업 중단");
        }
    }
    $pdo->exec('COMMIT');
    $push(['SET FOREIGN_KEY_CHECKS=1'], true);
    fclose($fh);
    rename($path . '.part', $path);
    @chmod($path, 0600);
    return ['path' => $path, 'tables' => count($tables), 'rows' => $rowsTotal, 'bytes' => (int)filesize($path)];
}

/** 오래된 백업 정리 — 최신 N개만 남긴다 */
function backup_prune(int $keep = BACKUP_KEEP): int
{
    $files = glob(backup_dir() . DIRECTORY_SEPARATOR . 'jlportal_*.jlbk') ?: [];
    rsort($files);
    $n = 0;
    foreach (array_slice($files, $keep) as $f) {
        if (@unlink($f)) {
            $n++;
        }
    }
    return $n;
}

/**
 * 백업 파일 → SQL 문 목록을 조각 단위로 돌려준다 (메모리 절약).
 * @return Generator<array<string>>
 */
function backup_read(string $path, string $key): Generator
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException('백업 파일을 열 수 없습니다.');
    }
    $magic = fread($fh, strlen(BACKUP_MAGIC));
    if ($magic !== BACKUP_MAGIC) {
        throw new RuntimeException('제이엘 포털 백업 파일이 아닙니다.');
    }
    $header = fread($fh, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
    $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
    $final = false;
    while (!$final) {
        $lenBin = fread($fh, 4);
        if ($lenBin === false || strlen($lenBin) !== 4) {
            throw new RuntimeException('백업 파일이 중간에 끊겼습니다(마지막 조각 없음).');
        }
        $c = fread($fh, unpack('N', $lenBin)[1]);
        $r = sodium_crypto_secretstream_xchacha20poly1305_pull($state, (string)$c);
        if ($r === false) {
            throw new RuntimeException('복호화 실패 — 키가 다르거나 파일이 손상·변조됐습니다.');
        }
        [$plain, $tag] = $r;
        $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        $stmts = json_decode((string)gzuncompress($plain), true);
        if (!is_array($stmts)) {
            throw new RuntimeException('백업 조각을 읽지 못했습니다.');
        }
        yield $stmts;
    }
    fclose($fh);
}

/** 복원 — 파일의 SQL 을 순서대로 실행 (CLI 전용, 원본 덤프 그대로라 prepared 불가) */
function backup_restore(string $path): array
{
    $pdo = db();
    $n = 0;
    foreach (backup_read($path, backup_key()) as $stmts) {
        foreach ($stmts as $sql) {
            if (str_starts_with($sql, '-- ')) {
                continue;
            }
            $pdo->exec($sql);
            $n++;
        }
    }
    return ['statements' => $n];
}

/** SQL 파일로만 풀기 (mysql 클라이언트로 직접 넣고 싶을 때) */
function backup_decrypt_to(string $path, string $out): int
{
    $fh = fopen($out, 'wb');
    $n = 0;
    foreach (backup_read($path, backup_key()) as $stmts) {
        foreach ($stmts as $sql) {
            fwrite($fh, $sql . (str_starts_with($sql, '-- ') ? "\n" : ";\n"));
            $n++;
        }
    }
    fclose($fh);
    return $n;
}
