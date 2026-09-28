<?php
/**
 * 등기진행 상세/수정 — 기존 registration_form 재현.
 * ①인적사항 ②8단계(날짜+완료) ③비용 11항목 ④입금(차액 자동)
 * ⑤채권환불 신청(복호화 표시 + 환불일 입력) ⑥미비서류 ⑦관리자메모
 */

$staff = staff_require();

$hid = (int)($_GET['hid'] ?? ($_POST['hid'] ?? 0));
$hh = db_row('SELECT h.*, c.name AS complex_name FROM household h JOIN complex c ON c.id = h.complex_id WHERE h.id = ?', [$hid]);
if ($hh === null) {
    http_response_code(404);
    render_admin('error', ['pageTitle' => '세대 없음', 'staff' => $staff,
        'message' => '해당 세대를 찾을 수 없습니다. (hid=' . $hid . ')']);
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'progress') {
        // ② 8단계 + ⑥ 미비서류 + ⑦ 관리자메모
        $cols = ['household_id' => $hid];
        $summary = [];
        for ($n = 1; $n <= 8; $n++) {
            $date = trim((string)($_POST["step{$n}_date"] ?? ''));
            $done = !empty($_POST["step{$n}_done"]);
            if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $error = "{$n}단계 날짜 형식이 올바르지 않습니다.";
                break;
            }
            if ($date !== '') {
                $done = true;                       // 날짜가 있으면 완료
            }
            $cols["step{$n}_date"] = $date !== '' ? $date : null;
            $cols["step{$n}_done"] = $done ? 1 : 0;
            if ($done) {
                $summary[] = (string)$n;
            }
        }
        if ($error === '') {
            $cols['missing_docs'] = trim((string)($_POST['missing_docs'] ?? '')) ?: null;
            $cols['memo_admin']   = trim((string)($_POST['memo_admin'] ?? '')) ?: null;
            $names = array_keys($cols);
            $set = implode(', ', array_map(fn($c) => "$c = VALUES($c)", array_slice($names, 1)));
            db_exec('INSERT INTO progress (' . implode(',', $names) . ') VALUES ('
                . implode(',', array_fill(0, count($names), '?')) . ") ON DUPLICATE KEY UPDATE $set",
                array_values($cols));
            staff_audit((int)$staff['id'], 'progress.save', "household:$hid",
                '완료단계 [' . implode(',', $summary) . ']');
            $notice = '진행 단계·미비서류·메모를 저장했습니다.';
        }
    } elseif ($act === 'cost') {
        // ③ 비용 + ④ 입금 — 차액 = 입금액 - 합계 자동 계산
        $vals = ['household_id' => $hid];
        foreach (cost_item_labels() as $col => $label) {
            $raw = str_replace([',', ' '], '', (string)($_POST[$col] ?? '0'));
            if ($raw === '') {
                $raw = '0';
            }
            if (!is_numeric($raw)) {
                $error = "{$label} 금액이 숫자가 아닙니다.";
                break;
            }
            $vals[$col] = (int)round((float)$raw);
        }
        if ($error === '') {
            $total = array_sum(array_slice($vals, 1));
            $paidRaw = str_replace([',', ' '], '', (string)($_POST['paid_amount'] ?? '0')) ?: '0';
            $paidDate = trim((string)($_POST['paid_date'] ?? ''));
            if (!is_numeric($paidRaw)) {
                $error = '입금액이 숫자가 아닙니다.';
            } elseif ($paidDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate)) {
                $error = '입금일 형식이 올바르지 않습니다.';
            } else {
                $paid = (int)round((float)$paidRaw);
                $vals['total'] = $total;
                $vals['paid_date'] = $paidDate !== '' ? $paidDate : null;
                $vals['paid_amount'] = $paid;
                $vals['diff'] = $paid - $total;
                $names = array_keys($vals);
                $set = implode(', ', array_map(fn($c) => "$c = VALUES($c)", array_slice($names, 1)));
                db_exec('INSERT INTO cost (' . implode(',', $names) . ') VALUES ('
                    . implode(',', array_fill(0, count($names), '?')) . ") ON DUPLICATE KEY UPDATE $set",
                    array_values($vals));
                staff_audit((int)$staff['id'], 'cost.save', "household:$hid",
                    '합계 ' . number_format($total) . ' 입금 ' . number_format($paid));
                $notice = '비용·입금 내역을 저장했습니다.';
            }
        }
    } elseif ($act === 'refund_date') {
        // ⑤ 환불일 입력 (직원)
        $rid = (int)($_POST['refund_id'] ?? 0);
        $date = trim((string)($_POST['refund_date'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = '환불일 형식이 올바르지 않습니다.';
        } else {
            db_exec('UPDATE refund_request SET refund_date = ? WHERE id = ? AND household_id = ?',
                [$date !== '' ? $date : null, $rid, $hid]);
            staff_audit((int)$staff['id'], 'refund.date', "refund:$rid",
                $date !== '' ? "환불일 $date" : '환불일 지움');
            $notice = '환불일을 저장했습니다.';
        }
    }
}

$owners  = db_all('SELECT * FROM owner WHERE household_id = ? ORDER BY is_primary DESC, id', [$hid]);
$prog    = db_row('SELECT * FROM progress WHERE household_id = ?', [$hid]);
$cost    = db_row('SELECT * FROM cost WHERE household_id = ?', [$hid]);
$refund  = db_row('SELECT * FROM refund_request WHERE household_id = ? ORDER BY id DESC LIMIT 1', [$hid]);
if ($refund !== null) {
    $refund['account'] = decrypt_field($refund['account_enc']) ?? '(복호화 실패)';
}
$address = db_row('SELECT * FROM address_request WHERE household_id = ? ORDER BY id DESC LIMIT 1', [$hid]);
if ($address !== null) {
    $address['addr1'] = decrypt_field($address['addr1_enc']) ?? '(복호화 실패)';
    $address['addr2'] = decrypt_field($address['addr2_enc']) ?? '';
}

render_admin('reg_form', [
    'pageTitle' => '등기진행 상세 — ' . $hh['dong'] . '동 ' . $hh['ho'] . '호',
    'staff'     => $staff,
    'hh'        => $hh,
    'owners'    => $owners,
    'prog'      => $prog,
    'cost'      => $cost,
    'refund'    => $refund,
    'address'   => $address,
    'notice'    => $notice,
    'error'     => $error,
]);
