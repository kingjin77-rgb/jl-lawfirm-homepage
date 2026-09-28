<?php
/**
 * 대시보드 — 단지/세대/단계별 합계 + 최근 손님 입력 5건(마스킹) + 빠른 이동.
 * 주소·계좌 전문은 여기 안 띄운다. 전용 현황 화면에서만 복호화 전문을 본다.
 */

$staff = staff_require();

$counts = [
    'complex'   => (int)(db_row('SELECT COUNT(*) AS n FROM complex')['n'] ?? 0),
    'household' => (int)(db_row('SELECT COUNT(*) AS n FROM household')['n'] ?? 0),
    'address'   => (int)(db_row('SELECT COUNT(*) AS n FROM address_request')['n'] ?? 0),
    'refund'    => (int)(db_row('SELECT COUNT(*) AS n FROM refund_request')['n'] ?? 0),
];

// 진행단계별 완료 세대 수
$sel = [];
for ($n = 1; $n <= 8; $n++) {
    $sel[] = "SUM(CASE WHEN step{$n}_date IS NOT NULL OR step{$n}_done = 1 THEN 1 ELSE 0 END) AS s{$n}";
}
$stepRow = db_row('SELECT ' . implode(', ', $sel) . ' FROM progress') ?? [];
$stepCounts = [];
foreach (progress_step_names() as $n => $label) {
    $stepCounts[$n] = ['label' => $label, 'count' => (int)($stepRow["s$n"] ?? 0)];
}

// 최근 손님 입력 5건 — 주소·환불 합쳐 최신순. 미리보기는 마스킹만.
$recentAddr = db_all(
    'SELECT a.id, a.created_at, a.addr1_enc, h.dong, h.ho, c.name AS complex_name
       FROM address_request a
       JOIN household h ON h.id = a.household_id
       JOIN complex c ON c.id = h.complex_id
      ORDER BY a.id DESC LIMIT 5');
$recentRefund = db_all(
    'SELECT r.id, r.created_at, r.bank, r.account_enc, h.dong, h.ho, c.name AS complex_name
       FROM refund_request r
       JOIN household h ON h.id = r.household_id
       JOIN complex c ON c.id = h.complex_id
      ORDER BY r.id DESC LIMIT 5');
$recent = [];
foreach ($recentAddr as $a) {
    $plain = decrypt_field($a['addr1_enc']);
    $recent[] = [
        'type'    => '권리증 주소',
        'where'   => $a['complex_name'] . ' ' . $a['dong'] . '동 ' . $a['ho'] . '호',
        'preview' => $plain === null ? '(복호화 실패)' : mask_address($plain),
        'at'      => $a['created_at'],
    ];
}
foreach ($recentRefund as $r) {
    $plain = decrypt_field($r['account_enc']);
    $recent[] = [
        'type'    => '채권 환불',
        'where'   => $r['complex_name'] . ' ' . $r['dong'] . '동 ' . $r['ho'] . '호',
        'preview' => $plain === null ? '(복호화 실패)' : mask_account($r['bank'], $plain),
        'at'      => $r['created_at'],
    ];
}
usort($recent, fn($x, $y) => strcmp($y['at'], $x['at']));
$recent = array_slice($recent, 0, 5);

render_admin('dashboard', [
    'pageTitle'  => '대시보드',
    'staff'      => $staff,
    'counts'     => $counts,
    'stepCounts' => $stepCounts,
    'recent'     => $recent,
]);
