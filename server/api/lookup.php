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
$dong    = substr(preg_replace('/[^0-9]/', '', (string)($in['dong'] ?? '')), 0, 10);
$ho      = substr(preg_replace('/[^0-9]/', '', (string)($in['ho'] ?? '')), 0, 10);
$name    = norm_name((string)($in['name'] ?? ''));
$birth   = (string)($in['birth'] ?? '');

// 생년월일은 숫자 여섯 자리만 받는다. 섞인 글자를 걸러 맞춰 주면 관리자 화면 해시와 어긋난다.
if ($complex === '' || $dong === '' || $ho === '' || $name === '' || !preg_match('/^\d{6}$/', $birth)) {
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
        'message' => '조회를 여러 번 시도하셨습니다. 잠시 뒤 다시 시도해 주십시오. 문의 1899-4252',
    ], 429);
}

/* ── 조회 ────────────────────────────────── */
$st = db()->prepare('SELECT id, is_open FROM complexes WHERE name = ? LIMIT 1');
$st->execute([$complex]);
$cx = $st->fetch();

$log = db()->prepare('INSERT INTO lookup_log (ip_hash, complex_id, dong, ho, ok) VALUES (?, ?, ?, ?, ?)');
prune_log();

if (!$cx) {
    $log->execute([$iph, null, $dong, $ho, 0]);
    json_out(['ok' => false, 'message' => '선택하신 아파트를 찾지 못했습니다.']);
}

/* ── 세대별 잠금 ────────────────────────────
 * IP 제한만으로는 여러 곳에서 나눠 두드리면 한 세대를 계속 맞춰 볼 수 있다.
 * 한 세대에 실패가 쌓이면 어디서 오든 그 세대 조회를 잠시 닫는다. */
$unitWin  = (int)($rate['unit_window_min'] ?? 60);
$unitFail = (int)($rate['unit_max_fail'] ?? 10);

$st = db()->prepare(
    'SELECT COUNT(*) FROM lookup_log
      WHERE complex_id = ? AND dong = ? AND ho = ? AND ok = 0 AND at > (NOW() - INTERVAL ? MINUTE)'
);
$st->execute([$cx['id'], $dong, $ho, $unitWin]);

if ((int)$st->fetchColumn() >= $unitFail) {
    json_out([
        'ok'      => false,
        'message' => '이 세대는 조회 실패가 여러 번 있어 잠시 조회를 막아 두었습니다. 한 시간쯤 뒤 다시 시도해 주십시오. 문의 1899-4252',
    ], 429);
}

if ((int)$cx['is_open'] !== 1) {
    $log->execute([$iph, $cx['id'], $dong, $ho, 0]);
    json_out([
        'ok'      => false,
        'message' => '이 아파트는 등기가 모두 끝나 온라인 조회를 닫았습니다. 문의 1899-4252',
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
    $log->execute([$iph, $cx['id'], $dong, $ho, 0]);
    // 어느 항목이 틀렸는지 알려주지 않는다. 알려주면 하나씩 맞춰 볼 수 있다.
    json_out([
        'ok'      => false,
        'message' => '조회되지 않았습니다. 동과 호, 계약자 성함과 생년월일을 다시 확인해 주십시오. '
                   . '계약자가 다른 분 명의인 경우에도 조회되지 않습니다.',
    ]);
}

$log->execute([$iph, $cx['id'], $dong, $ho, 1]);

$items = json_decode((string)($hh['cost_items'] ?? ''), true);

// 입금액이 있으면 차액은 늘 두 숫자에서 다시 뺀다. 올라온 diff 가 비어 있어도 틀리지 않게.
$total = (int)$hh['cost_total'];
$paid  = (int)$hh['cost_paid'];
$diff  = ($paid > 0 && $total > 0) ? $paid - $total : (int)$hh['cost_diff'];

json_out([
    'ok'       => true,
    'complex'  => $complex,
    'dong'     => $dong,
    'ho'       => $ho,
    'step'     => $hh['step'],
    'at'       => $hh['step_at'],
    'memo'     => $hh['memo'],
    'total'    => $total,
    'paid'     => $paid,
    'diff'     => $diff,
    'paidAt'   => $hh['paid_at'],
    'items'    => is_array($items) ? $items : [],
    'certSent' => (bool)$hh['cert_sent'],
    'poa'      => (bool)$hh['has_poa'],
]);
