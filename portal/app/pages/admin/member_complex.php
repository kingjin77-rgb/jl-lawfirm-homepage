<?php
/**
 * 아파트 관리 — 목록(회원수·등기진행수) + 등록/수정.
 * 수납계좌는 회사 계좌(손님에게 안내하는 값)라 평문 저장한다.
 */

$staff = staff_require();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $id      = (int)($_POST['id'] ?? 0);
    $name    = trim((string)($_POST['name'] ?? ''));
    $exposed = (int)!empty($_POST['exposed']);
    $bank    = trim((string)($_POST['bank_name'] ?? ''));
    $account = trim((string)($_POST['bank_account'] ?? ''));
    $holder  = trim((string)($_POST['bank_holder'] ?? ''));
    $sort    = (int)($_POST['sort'] ?? 0);

    if ($name === '') {
        $error = '아파트명을 넣어 주십시오.';
    } elseif ($id > 0) {
        db_exec('UPDATE complex SET name=?, exposed=?, bank_name=?, bank_account=?, bank_holder=?, sort=? WHERE id=?',
            [$name, $exposed, $bank, $account, $holder, $sort, $id]);
        staff_audit((int)$staff['id'], 'complex.update', "complex:$id", $name);
        $notice = "「{$name}」 정보를 수정했습니다.";
    } else {
        db_exec('INSERT INTO complex (name, exposed, bank_name, bank_account, bank_holder, sort) VALUES (?,?,?,?,?,?)',
            [$name, $exposed, $bank, $account, $holder, $sort]);
        staff_audit((int)$staff['id'], 'complex.create', 'complex:' . db_insert_id(), $name);
        $notice = "「{$name}」 를 등록했습니다.";
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId > 0 ? db_row('SELECT * FROM complex WHERE id = ?', [$editId]) : null;

$list = db_all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM household h WHERE h.complex_id = c.id) AS household_cnt,
            (SELECT COUNT(*) FROM owner o JOIN household h2 ON h2.id = o.household_id
              WHERE h2.complex_id = c.id) AS member_cnt,
            (SELECT COUNT(*) FROM progress p JOIN household h3 ON h3.id = p.household_id
              WHERE h3.complex_id = c.id) AS progress_cnt
       FROM complex c ORDER BY c.sort, c.id');

render_admin('member_complex', [
    'pageTitle' => '아파트 관리',
    'staff'     => $staff,
    'list'      => $list,
    'edit'      => $edit,
    'notice'    => $notice,
    'error'     => $error,
]);
