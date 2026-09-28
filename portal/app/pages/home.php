<?php
/** 손님 메인 — 기존 /main/index.php 의 6개 카드 재현 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

render('home', [
    'pageTitle' => '나의 등기',
    'customer'  => $customer,
    'hh'        => $hh,
]);
