<?php
/**
 * 등기비용 내역서 — 11항목 + 합계/입금액/차액.
 * 0원 항목은 숨기고 합계는 항상 보인다. 기록이 없으면 "준비중입니다".
 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

$cost = db_row('SELECT * FROM cost WHERE household_id = ?', [$customer['household_id']]);

$items = [];
if ($cost !== null) {
    foreach (cost_item_labels() as $col => $label) {
        $amt = (int)$cost[$col];
        if ($amt > 0) {
            $items[] = ['label' => $label, 'amount' => $amt];
        }
    }
}

render('cost', [
    'pageTitle' => '등기비용 내역서',
    'customer'  => $customer,
    'hh'        => $hh,
    'cost'      => $cost,
    'items'     => $items,
]);
