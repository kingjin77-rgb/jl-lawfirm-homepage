<?php
/* 조회 화면의 아파트 목록 — 서버가 실제로 가진 단지만 내려준다.
 * 자료가 아직 안 올라온 단지를 고르면 고객이 헛걸음한다. */
declare(strict_types=1);
require __DIR__ . '/_common.php';   // 이 파일도 api/ 안에 있다
cors();
$rows = db()->query('SELECT name FROM complexes WHERE is_open = 1 ORDER BY name')->fetchAll();
json_out(['ok' => true, 'complexes' => array_column($rows, 'name')]);
