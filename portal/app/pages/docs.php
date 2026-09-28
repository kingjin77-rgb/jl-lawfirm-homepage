<?php
/** 미비서류 현황 — 목록(없으면 "개별 미비서류가 없습니다") + 발급기준 안내 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

$prog = db_row('SELECT missing_docs FROM progress WHERE household_id = ?', [$customer['household_id']]);

// 미비서류는 줄 단위 텍스트 — 빈 줄은 걸러 목록으로 만든다.
$missing = [];
if ($prog !== null && trim((string)$prog['missing_docs']) !== '') {
    foreach (preg_split('/\R/u', (string)$prog['missing_docs']) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $missing[] = $line;
        }
    }
}

render('docs', [
    'pageTitle' => '미비서류 현황',
    'customer'  => $customer,
    'hh'        => $hh,
    'missing'   => $missing,
]);
