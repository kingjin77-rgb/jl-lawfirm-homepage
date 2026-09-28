<?php
/**
 * 현재 필터 조건의 등기진행 목록 CSV 내려받기 (BOM 포함, 엑셀에서 바로 열림).
 * 주소·계좌 같은 암호화 항목은 내보내지 않는다 (전용 화면에서만 열람).
 */

$staff = staff_require();

$f = reg_filters_from_get();
[$whereSql, $params] = reg_filters_where($f);

$rows = db_all(
    'SELECT h.id, h.dong, h.ho, c.name AS complex_name, p.*, k.acq_tax, k.bond_transfer, k.bond_mortgage,
            k.stamp_tax, k.cert_stamp, k.via_stamp, k.trust_cancel, k.cert_fees, k.fee, k.vat, k.etc_amt,
            k.total, k.paid_date, k.paid_amount, k.diff
       FROM household h
       JOIN complex c ON c.id = h.complex_id
       LEFT JOIN progress p ON p.household_id = h.id
       LEFT JOIN cost k ON k.household_id = h.id
      WHERE ' . $whereSql . '
      ORDER BY c.name, CAST(h.dong AS UNSIGNED), h.dong, CAST(h.ho AS UNSIGNED), h.ho', $params);

$ids = array_column($rows, 'id');
$ownersBy = [];
if ($ids !== []) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT household_id, name, birth6, phone FROM owner
                      WHERE household_id IN ($in) ORDER BY is_primary DESC, id", $ids) as $o) {
        $ownersBy[(int)$o['household_id']][] = $o;
    }
}

staff_audit((int)$staff['id'], 'export.csv', 'registration', count($rows) . '행 내보내기');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="registration_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");                     // UTF-8 BOM — 엑셀 한글 대응

$head = ['아파트', '동', '호수', '성명', '성명2', '생년월일', '생년월일2', '핸드폰', '핸드폰2'];
foreach (progress_step_names() as $n => $label) {
    $head[] = $n . '.' . $label;
}
foreach (cost_item_labels() as $label) {
    $head[] = $label;
}
array_push($head, '합계', '입금일', '입금액', '차액', '미비서류');
fputcsv($out, $head);

foreach ($rows as $r) {
    $owners = $ownersBy[(int)$r['id']] ?? [];
    $line = [
        $r['complex_name'], $r['dong'], $r['ho'],
        $owners[0]['name'] ?? '', $owners[1]['name'] ?? '',
        $owners[0]['birth6'] ?? '', $owners[1]['birth6'] ?? '',
        $owners[0]['phone'] ?? '', $owners[1]['phone'] ?? '',
    ];
    for ($n = 1; $n <= 8; $n++) {
        $line[] = progress_step_complete($r, $n) ? ($r["step{$n}_date"] ?? '완료') : '';
    }
    foreach (array_keys(cost_item_labels()) as $col) {
        $line[] = (int)($r[$col] ?? 0);
    }
    array_push($line, (int)($r['total'] ?? 0), (string)($r['paid_date'] ?? ''),
        (int)($r['paid_amount'] ?? 0), (int)($r['diff'] ?? 0), (string)($r['missing_docs'] ?? ''));
    fputcsv($out, $line);
}
fclose($out);
exit;
