<?php
/**
 * PDO 래퍼 — 모든 쿼리는 prepared statement 로만 나간다.
 * 문자열 이어붙이기 쿼리를 원천 차단하기 위해 raw PDO 를 밖으로 내주지 않고
 * db_query / db_row / db_all / db_exec / db_insert_id 만 쓴다.
 */

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $c = portal_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'], (int)($c['port'] ?? 3306), $c['name'], $c['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // 진짜 prepared statement 만 사용
    ]);
    return $pdo;
}

/** prepared 실행 후 statement 반환 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** 한 행 (없으면 null) */
function db_row(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** 전체 행 */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** INSERT/UPDATE/DELETE — 영향받은 행 수 반환 */
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

function db_insert_id(): int
{
    return (int)db()->lastInsertId();
}
