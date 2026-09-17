<?php
/* 등기 진행 조회 — 고객이 부르는 창구
 *
 * 받는 것 : {complex, dong, ho, name, birth}
 * 주는 것 : {ok, complex, dong, ho, step, at, memo}
 *
 * 이름과 생년월일은 데이터베이스에 없다. 해시로 바꿔 맞는지만 본다.
 * 이름과 생년월일은 찍어서 맞힐 수 있으므로 같은 곳에서 계속 두드리면 막는다.
 */

declare(strict_types=1);
require __DIR__ . '/_common.php';

cors();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'message' => '잘못된 요청입니다.'], 405);
}

$in      = body_json();
$complex = trim((string)($in['complex'] ?? ''));
$dong    = preg_replace('/[^0-9]/', '', (string)($in['dong'] ?? ''));
$ho      = preg_replace('/[^0-9]/', '', (string)($in['ho'] ?? ''));
$name    = trim((string)($in['name'] ?? ''));
$birth   = preg_replace('/[^0-9]/', '', (string)($in['birth'] ?? ''));

if ($complex === '' || $dong === '' || $ho === '' || $name === '' || strlen($birth) !== 6) {
    json_out(['ok' => false, 'message' => '입력하신 내용을 다시 확인해 주십시오.'], 400);
}

/* ── 두드림 막기 ──────────────────────────── */
$iph  = ip_hash();
$rate = cfg()['rate'];

$st = db()->prepare(
    'SELECT SUM(ok = 0) AS fails, COUNT(*) AS total
       FROM lookup_log
      WHERE ip_hash = ? AND at > (NOW() - INTERVAL ? MINUTE)'
);
$st->execute([$iph, $rate['window_min']]);
$r = $st->fetch() ?: ['fails' => 0, 'total' => 0];

if ((int)$r['fails'] >= $rate['max_fail'] || (int)$r['total'] >= $rate['max_total']) {
    json_out([
        'ok'      => false,
        'message' => '조회를 여러 번 시도하셨습니다. 잠시 뒤 다시 시도하시거나 1899-4252로 연락 주십시오.',
    ], 429);
}

/* ── 조회 ────────────────────────────────── */
$st = db()->prepare('SELECT id, is_open FROM complexes WHERE name = ? LIMIT 1');
$st->execute([$complex]);
$cx = $st->fetch();

$log = db()->prepare('INSERT INTO lookup_log (ip_hash, complex_id, ok) VALUES (?, ?, ?)');

if (!$cx) {
    $log->execute([$iph, null, 0]);
    json_out(['ok' => false, 'message' => '선택하신 아파트를 찾지 못했습니다.']);
}

if ((int)$cx['is_open'] !== 1) {
    $log->execute([$iph, $cx['id'], 0]);
    json_out([
        'ok'      => false,
        'message' => '이 아파트는 등기가 모두 끝나 온라인 조회를 닫았습니다. 1899-4252로 연락 주십시오.',
    ]);
}

$st = db()->prepare(
    'SELECT h.step, h.memo, h.step_at, h.cost_total, h.cost_paid, h.cost_diff, h.paid_at, h.cost_items,
            h.cert_sent, h.has_poa
       FROM households h
       JOIN household_keys k ON k.household_id = h.id
      WHERE h.complex_id = ? AND h.dong = ? AND h.ho = ? AND k.vhash = ?
      LIMIT 1'
);
// 공동명의면 두 사람 중 누구로 조회해도 맞는다. 열쇠가 세대마다 여럿 걸려 있다.
$st->execute([$cx['id'], $dong, $ho, verify_hash($name, $birth)]);
$hh = $st->fetch();

if (!$hh) {
    $log->execute([$iph, $cx['id'], 0]);
    // 어느 항목이 틀렸는지 알려주지 않는다. 알려주면 하나씩 맞춰 볼 수 있다.
    json_out([
        'ok'      => false,
        'message' => '조회되지 않았습니다. 동·호와 계약자 성함, 생년월일을 다시 확인해 주십시오. '
                   . '계약자가 다른 분 명의인 경우에도 조회되지 않습니다.',
    ]);
}

$log->execute([$iph, $cx['id'], 1]);

$items = json_decode((string)($hh['cost_items'] ?? ''), true);

json_out([
    'ok'       => true,
    'complex'  => $complex,
    'dong'     => $dong,
    'ho'       => $ho,
    'step'     => $hh['step'],
    'at'       => $hh['step_at'],
    'memo'     => $hh['memo'],
    'total'    => (int)$hh['cost_total'],
    'paid'     => (int)$hh['cost_paid'],
    'diff'     => (int)$hh['cost_diff'],
    'paidAt'   => $hh['paid_at'],
    'items'    => is_array($items) ? $items : [],
    'certSent' => (bool)$hh['cert_sent'],
    'poa'      => (bool)$hh['has_poa'],
]);
