<?php
/* 관리자 업로드 — 직원 화면에서 내보낸 파일을 받아 넣는다
 *
 * 받는 것 : 관리자 화면의 "서버 업로드본 내보내기" 로 만든 JSON 그대로
 *           {complex, steps, exportedAt, households:[{dong,ho,vhash,step,at,memo}]}
 * 머리글  : X-Upload-Token 에 config.php 의 upload_token
 *
 * 같은 단지를 다시 올리면 동·호로 맞춰 갱신하고 없던 세대만 넣는다.
 * 지우지는 않는다. 잘못 올렸을 때 세대가 통째로 사라지면 복구가 어렵다.
 */

declare(strict_types=1);
require __DIR__ . '/_common.php';

cors();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'message' => '잘못된 요청입니다.'], 405);
}

$token = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals((string)cfg()['upload_token'], (string)$token)) {
    json_out(['ok' => false, 'message' => '업로드 권한이 없습니다.'], 401);
}

$in      = body_json();
$complex = trim((string)($in['complex'] ?? ''));
$rows    = $in['households'] ?? null;

if ($complex === '' || !is_array($rows) || !$rows) {
    json_out(['ok' => false, 'message' => '올릴 자료가 없습니다.'], 400);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $st = $pdo->prepare('SELECT id FROM complexes WHERE name = ? LIMIT 1');
    $st->execute([$complex]);
    $cid = $st->fetchColumn();

    if (!$cid) {
        $pdo->prepare('INSERT INTO complexes (name) VALUES (?)')->execute([$complex]);
        $cid = (int)$pdo->lastInsertId();
    }

    $up = $pdo->prepare(
        'INSERT INTO households (complex_id, dong, ho, vhash, step, memo, step_at)
              VALUES (:c, :d, :h, :v, :s, :m, :a)
         ON DUPLICATE KEY UPDATE
              vhash = VALUES(vhash), step = VALUES(step),
              memo  = VALUES(memo),  step_at = VALUES(step_at)'
    );

    $ins = 0;
    $upd = 0;
    foreach ($rows as $r) {
        $dong = preg_replace('/[^0-9]/', '', (string)($r['dong'] ?? ''));
        $ho   = preg_replace('/[^0-9]/', '', (string)($r['ho'] ?? ''));
        $v    = strtolower(trim((string)($r['vhash'] ?? '')));

        // 해시가 아닌 값이 오면 넣지 않는다. 실수로 이름이 담겨 오는 것을 막는 빗장이다.
        if ($dong === '' || $ho === '' || !preg_match('/^[0-9a-f]{64}$/', $v)) {
            continue;
        }

        $at = (string)($r['at'] ?? '');
        $up->execute([
            ':c' => $cid,
            ':d' => $dong,
            ':h' => $ho,
            ':v' => $v,
            ':s' => mb_substr(trim((string)($r['step'] ?? '접수')), 0, 40),
            ':m' => mb_substr(trim((string)($r['memo'] ?? '')), 0, 500),
            ':a' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $at) ? $at : null,
        ]);
        // rowCount 는 새로 넣으면 1, 값이 바뀌면 2 를 준다
        if ($up->rowCount() === 1) {
            $ins++;
        } else {
            $upd++;
        }
    }

    $pdo->prepare('INSERT INTO upload_log (complex_id, n_insert, n_update) VALUES (?, ?, ?)')
        ->execute([$cid, $ins, $upd]);

    $pdo->commit();
    json_out(['ok' => true, 'complex' => $complex, 'inserted' => $ins, 'updated' => $upd]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[track] upload: ' . $e->getMessage());
    json_out(['ok' => false, 'message' => '저장하지 못했습니다. 잠시 뒤 다시 시도해 주십시오.'], 500);
}
