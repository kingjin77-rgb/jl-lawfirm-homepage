<?php
/** 자주묻는질문 — 분류 탭(등기·비용·서류·채권환불) + 아코디언. 0건이어도 동작. */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

$categories = ['전체', '등기', '비용', '서류', '채권환불'];
$cat = (string)($_GET['cat'] ?? '전체');
if (!in_array($cat, $categories, true)) {
    $cat = '전체';
}

if ($cat === '전체') {
    $faqs = db_all('SELECT category, question, answer FROM faq WHERE visible = 1
                    ORDER BY category, sort, id');
} else {
    $faqs = db_all('SELECT category, question, answer FROM faq WHERE visible = 1 AND category = ?
                    ORDER BY sort, id', [$cat]);
}

render('faq', [
    'pageTitle'  => '자주묻는질문',
    'customer'   => $customer,
    'hh'         => $hh,
    'categories' => $categories,
    'cat'        => $cat,
    'faqs'       => $faqs,
]);
