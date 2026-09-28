<?php
/**
 * 채권 환불 신청 — 은행(선택+직접입력) · 계좌번호 · 예금주.
 * 계좌번호는 암호화 저장. 저장 사실만 customer_log 에 남긴다.
 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

// 흔한 은행 목록 — select 에 없으면 '직접입력' 으로 받는다.
$banks = ['국민은행','신한은행','우리은행','하나은행','농협은행','기업은행','SC제일은행',
          '카카오뱅크','케이뱅크','토스뱅크','새마을금고','신협','우체국','수협은행','부산은행',
          '대구은행','광주은행','전북은행','경남은행','제주은행'];

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $bank = trim((string)($_POST['bank'] ?? ''));
    if ($bank === '직접입력') {
        $bank = trim((string)($_POST['bank_etc'] ?? ''));
    }
    $account = preg_replace('/[^0-9\-]/', '', (string)($_POST['account'] ?? ''));
    $holder  = trim((string)($_POST['holder'] ?? ''));

    if ($bank === '' || $account === '' || $holder === '') {
        $error = '은행, 계좌번호, 예금주를 모두 채워 주십시오.';
    } elseif (mb_strlen($bank) > 30 || strlen($account) > 30 || mb_strlen($holder) > 30) {
        $error = '입력값이 너무 깁니다. 다시 확인해 주십시오.';
    } else {
        db_exec(
            'INSERT INTO refund_request (household_id, bank, account_enc, holder) VALUES (?,?,?,?)',
            [$customer['household_id'], $bank, encrypt_field($account), $holder]
        );
        customer_log_action($customer['household_id'], $customer['owner_name'], 'refund.save');
        $saved = true;
    }
}

$current = db_row(
    'SELECT bank, account_enc, holder, refund_date, created_at FROM refund_request
      WHERE household_id = ? ORDER BY id DESC LIMIT 1',
    [$customer['household_id']]
);
if ($current !== null) {
    $current['account'] = decrypt_field($current['account_enc']) ?? '(복호화 실패)';
}

render('refund', [
    'pageTitle' => '채권 환불 신청',
    'customer'  => $customer,
    'hh'        => $hh,
    'banks'     => $banks,
    'current'   => $current,
    'saved'     => $saved,
    'error'     => $error,
]);
