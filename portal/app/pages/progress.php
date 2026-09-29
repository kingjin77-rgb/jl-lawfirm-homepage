<?php
/**
 * 등기진행 현황 — STEP 01~08 카드.
 * 날짜가 있으면 완료, 완료 다음 첫 단계가 진행중, 나머지는 준비중.
 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

$prog = db_row('SELECT * FROM progress WHERE household_id = ?', [$customer['household_id']]);

$steps = [];
$doneUpTo = 0; // 마지막으로 완료된 단계 (날짜 또는 완료 표시)
foreach (progress_step_names() as $n => $label) {
    $done = progress_step_complete($prog, $n);
    if ($done) {
        $doneUpTo = $n;
    }
    $steps[$n] = ['label' => $label, 'date' => $prog['step' . $n . '_date'] ?? null, 'done' => $done];
}
foreach ($steps as $n => &$s) {
    if ($s['done']) {
        $s['state'] = '완료';
    } elseif ($n === $doneUpTo + 1) {
        $s['state'] = '진행중';
    } else {
        $s['state'] = '준비중';
    }
}
unset($s);

render('progress', [
    'pageTitle' => '등기진행 현황',
    'customer'  => $customer,
    'hh'        => $hh,
    'steps'     => $steps,
    'hasRecord' => $prog !== null,
    'sentDate'  => $prog['sent_date'] ?? null,
]);
