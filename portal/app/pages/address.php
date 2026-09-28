<?php
/**
 * 권리증 수령 주소 — 현재 등록분(복호화해서 표시) + 저장 폼.
 * 주소는 암호화해서만 저장하고, 저장 사실만 customer_log 에 남긴다.
 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $zip   = preg_replace('/\D/', '', (string)($_POST['zip'] ?? ''));
    $addr1 = trim((string)($_POST['addr1'] ?? ''));
    $addr2 = trim((string)($_POST['addr2'] ?? ''));

    if ($zip === '' || $addr1 === '') {
        $error = '우편번호와 주소를 채워 주십시오.';
    } elseif (mb_strlen($addr1) > 200 || mb_strlen($addr2) > 200) {
        $error = '주소가 너무 깁니다. 200자 이내로 넣어 주십시오.';
    } else {
        db_exec(
            'INSERT INTO address_request (household_id, zip, addr1_enc, addr2_enc) VALUES (?,?,?,?)',
            [$customer['household_id'], $zip, encrypt_field($addr1), encrypt_field($addr2)]
        );
        customer_log_action($customer['household_id'], $customer['owner_name'], 'address.save');
        $saved = true;
    }
}

// 최신 등록분을 복호화해 보여준다 (본인 세대 것만).
$current = db_row(
    'SELECT zip, addr1_enc, addr2_enc, created_at FROM address_request
      WHERE household_id = ? ORDER BY id DESC LIMIT 1',
    [$customer['household_id']]
);
if ($current !== null) {
    $current['addr1'] = decrypt_field($current['addr1_enc']) ?? '(복호화 실패)';
    $current['addr2'] = decrypt_field($current['addr2_enc']) ?? '';
}

render('address', [
    'pageTitle' => '권리증 수령 주소',
    'customer'  => $customer,
    'hh'        => $hh,
    'current'   => $current,
    'saved'     => $saved,
    'error'     => $error,
]);
