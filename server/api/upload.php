<?php
/* 직원 자료 반영 — 등기업무 시스템의 「서버 업로드본」을 받아 넣는다
 *
 * 받는 것 : {complex, steps, exportedAt, saltCheck?, households:[{dong, ho, vhash:[...], step, at, memo,
 *            total, paid, diff, paidAt, items:[{k,v}], lack, certSent, poa}]}
 * 머리글  : X-Upload-Token 에 config.php 의 upload_token
 *
 * 같은 단지를 다시 올리면 동·호로 맞춰 갱신한다.
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

/* 확인용 문구 대조.
 * 관리자 화면이 넣어 주는 saltCheck = sha256(verify_salt + '|chk').
 * 문구를 한 글자라도 다르게 넣고 내보낸 파일이면 해시가 전부 어긋나 아무도 조회되지 않는다.
 * 그런 파일은 넣기 전에 돌려보낸다. 옛 업로드본처럼 saltCheck 가 없으면 대조를 건너뛴다. */
if (isset($in['saltCheck'])) {
    $chk = strtolower(trim((string)$in['saltCheck']));
    if (!hash_equals(hash('sha256', salt() . '|chk'), $chk)) {
        json_out([
            'ok'      => false,
            'message' => '확인용 문구가 서버 설정과 다릅니다. 올바른 문구로 다시 내보낸 뒤 올려 주십시오.',
        ], 409);
    }
}

function num($v): int
{
    return is_numeric($v) ? (int)round((float)$v) : 0;
}

function day($v): ?string
{
    $s = (string)$v;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
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

    $find = $pdo->prepare('SELECT id FROM households WHERE complex_id = ? AND dong = ? AND ho = ? LIMIT 1');
    $ins  = $pdo->prepare(
        'INSERT INTO households
           (complex_id, dong, ho, step, step_at, memo, cost_total, cost_paid, cost_diff, paid_at, cost_items,
            has_lack, cert_sent, has_poa)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $upd  = $pdo->prepare(
        'UPDATE households SET step=?, step_at=?, memo=?, cost_total=?, cost_paid=?, cost_diff=?, paid_at=?,
                cost_items=?, has_lack=?, cert_sent=?, has_poa=?
          WHERE id=?'
    );
    $delK = $pdo->prepare('DELETE FROM household_keys WHERE household_id = ?');
    $addK = $pdo->prepare('INSERT IGNORE INTO household_keys (household_id, vhash) VALUES (?, ?)');

    $nIns = 0;
    $nUpd = 0;
    $nSkip = 0;
    $nNoKey = 0;

    foreach ($rows as $r) {
        if (!is_array($r)) { $nSkip++; continue; }
        // 표의 칸이 VARCHAR(10) 이다. 넘치면 저장이 통째로 실패하므로 잘라 둔다.
        $dong = substr(preg_replace('/[^0-9]/', '', (string)($r['dong'] ?? '')), 0, 10);
        $ho   = substr(preg_replace('/[^0-9]/', '', (string)($r['ho'] ?? '')), 0, 10);
        if ($dong === '' || $ho === '') { $nSkip++; continue; }

        // 해시가 아닌 값은 넣지 않는다. 실수로 이름이 담겨 와도 서버에 들어가지 않게 하는 빗장이다.
        $keys = $r['vhash'] ?? [];
        if (!is_array($keys)) $keys = [$keys];
        $keys = array_values(array_filter(array_map(function ($h) {
            $h = strtolower(trim((string)$h));
            return preg_match('/^[0-9a-f]{64}$/', $h) ? $h : null;
        }, $keys)));

        // 명세는 항목 이름과 금액만. 다른 것이 섞여 오면 버린다.
        $items = [];
        foreach ((array)($r['items'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $k = mb_substr(trim((string)($it['k'] ?? '')), 0, 30);
            if ($k === '') continue;
            $items[] = ['k' => $k, 'v' => num($it['v'] ?? 0)];
        }

        // 차액이 비어 오면 입금액 - 합계로 채운다. 0 으로 두면 "정산이 맞았다" 로 읽힌다.
        $total = num($r['total'] ?? 0);
        $paid  = num($r['paid'] ?? 0);
        $diff  = (isset($r['diff']) && is_numeric($r['diff']))
            ? num($r['diff'])
            : ($paid > 0 ? $paid - $total : 0);

        $step = mb_substr(trim((string)($r['step'] ?? '')), 0, 40);

        $vals = [
            $step !== '' ? $step : '접수 전',
            day($r['at'] ?? ''),
            mb_substr(trim((string)($r['memo'] ?? '')), 0, 500),
            $total, $paid, $diff,
            day($r['paidAt'] ?? ''),
            json_encode($items, JSON_UNESCAPED_UNICODE),
            empty($r['lack']) ? 0 : 1,
            empty($r['certSent']) ? 0 : 1,
            empty($r['poa']) ? 0 : 1,
        ];

        $find->execute([$cid, $dong, $ho]);
        $hid = $find->fetchColumn();
        if ($hid) {
            $upd->execute(array_merge($vals, [$hid]));
            $nUpd++;
        } else {
            $ins->execute(array_merge([$cid, $dong, $ho], $vals));
            $hid = (int)$pdo->lastInsertId();
            $nIns++;
        }

        // 열쇠는 매번 새로 건다. 명의자가 바뀌면 옛 열쇠로는 더 조회되지 않아야 한다.
        // 다만 새 열쇠가 하나도 없으면(생년월일이 빠진 채 내보낸 경우 등) 옛 열쇠를 지우지 않는다.
        // 지우면 어제까지 조회되던 고객이 오늘 갑자기 막힌다. 몇 세대인지 돌려준다.
        if (!$keys) {
            $nNoKey++;
            continue;
        }
        $delK->execute([$hid]);
        foreach ($keys as $h) {
            $addK->execute([$hid, $h]);
        }
    }

    $pdo->prepare('INSERT INTO upload_log (complex_id, n_insert, n_update) VALUES (?, ?, ?)')
        ->execute([$cid, $nIns, $nUpd]);

    $pdo->commit();
    json_out([
        'ok'       => true,
        'complex'  => $complex,
        'inserted' => $nIns,
        'updated'  => $nUpd,
        'skipped'  => $nSkip,
        'nokey'    => $nNoKey,   // 열쇠 없이 온 세대. 새 세대라면 아직 아무도 조회할 수 없다
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[track] upload: ' . $e->getMessage());
    json_out(['ok' => false, 'message' => '저장하지 못했습니다. 잠시 뒤 다시 시도해 주십시오.'], 500);
}
