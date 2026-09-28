<?php
/**
 * 세대/소유자 관리 — 검색(아파트+동+호+이름) 목록 + 등록/수정 + 삭제(admin 전용).
 * 공동명의는 소유자 행을 여러 개 둔다. 생년월일6·핸드폰도 여기서 고친다.
 */

$staff = staff_require();

$notice = '';
$error = '';
$complexes = db_all('SELECT id, name FROM complex ORDER BY sort, id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'delete') {
        // 세대 삭제 — admin 권한 + 확인 체크 필수. 진행/비용/신청이 같이 지워진다(FK CASCADE).
        if (($staff['role'] ?? '') !== 'admin') {
            http_response_code(403);
            render_admin('error', ['pageTitle' => '권한 없음', 'staff' => $staff,
                'message' => '세대 삭제는 admin 권한 직원만 할 수 있습니다.']);
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if (empty($_POST['confirm'])) {
            $error = '삭제 확인에 체크해 주십시오.';
        } else {
            $hh = db_row('SELECT h.dong, h.ho, c.name FROM household h JOIN complex c ON c.id=h.complex_id WHERE h.id=?', [$id]);
            db_exec('DELETE FROM household WHERE id = ?', [$id]);
            staff_audit((int)$staff['id'], 'household.delete', "household:$id",
                $hh !== null ? "{$hh['name']} {$hh['dong']}동 {$hh['ho']}호" : '');
            $notice = '세대를 삭제했습니다 (진행·비용·신청 기록 포함).';
        }
    } elseif ($act === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $complexId = (int)($_POST['complex_id'] ?? 0);
        $dong      = trim((string)($_POST['dong'] ?? ''));
        $ho        = trim((string)($_POST['ho'] ?? ''));

        // 소유자 행 수집 — 이름 있는 행만
        $owners = [];
        foreach ((array)($_POST['owner_name'] ?? []) as $i => $nm) {
            $nm = trim((string)$nm);
            if ($nm === '') {
                continue;
            }
            $b6 = preg_replace('/\D/', '', (string)($_POST['owner_birth6'][$i] ?? ''));
            $owners[] = [
                'name'   => $nm,
                'birth6' => str_pad(substr($b6, 0, 6), 6, '0', STR_PAD_LEFT),
                'phone'  => trim((string)($_POST['owner_phone'][$i] ?? '')),
                'valid'  => strlen($b6) === 6,
            ];
        }

        if ($complexId <= 0 || $dong === '' || $ho === '') {
            $error = '아파트·동·호수를 모두 넣어 주십시오.';
        } elseif ($owners === []) {
            $error = '소유자를 한 명 이상 넣어 주십시오.';
        } elseif (in_array(false, array_column($owners, 'valid'), true)) {
            $error = '생년월일(주민번호 앞자리)은 숫자 6자리로 넣어 주십시오.';
        } else {
            $dup = db_row('SELECT id FROM household WHERE complex_id=? AND dong=? AND ho=? AND id<>?',
                [$complexId, $dong, $ho, $id]);
            if ($dup !== null) {
                $error = '같은 단지에 이미 있는 동·호수입니다. (세대 #' . (int)$dup['id'] . ')';
            } else {
                if ($id > 0) {
                    db_exec('UPDATE household SET complex_id=?, dong=?, ho=? WHERE id=?', [$complexId, $dong, $ho, $id]);
                    $hid = $id;
                    $action = 'household.update';
                    $notice = '세대 정보를 수정했습니다.';
                } else {
                    db_exec('INSERT INTO household (complex_id, dong, ho) VALUES (?,?,?)', [$complexId, $dong, $ho]);
                    $hid = db_insert_id();
                    $action = 'household.create';
                    $notice = '세대를 등록했습니다.';
                }
                db_exec('DELETE FROM owner WHERE household_id = ?', [$hid]);
                foreach ($owners as $i => $o) {
                    db_exec('INSERT INTO owner (household_id, name, birth6, phone, is_primary) VALUES (?,?,?,?,?)',
                        [$hid, $o['name'], $o['birth6'], $o['phone'], $i === 0 ? 1 : 0]);
                }
                staff_audit((int)$staff['id'], $action, "household:$hid",
                    "{$dong}동 {$ho}호 소유자 " . count($owners) . '명');
            }
        }
    }
}

// 수정 대상 로드
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $edit = db_row('SELECT * FROM household WHERE id = ?', [$editId]);
    if ($edit !== null) {
        $edit['owners'] = db_all('SELECT * FROM owner WHERE household_id = ? ORDER BY is_primary DESC, id', [$editId]);
    }
}
$showForm = $edit !== null || isset($_GET['new']) || $error !== '';

// 검색 목록
$f = [
    'complex_id' => (int)($_GET['complex_id'] ?? 0),
    'dong'       => trim((string)($_GET['dong'] ?? '')),
    'ho'         => trim((string)($_GET['ho'] ?? '')),
    'name'       => trim((string)($_GET['name'] ?? '')),
];
$where = ['1=1'];
$params = [];
if ($f['complex_id'] > 0) { $where[] = 'h.complex_id = ?'; $params[] = $f['complex_id']; }
if ($f['dong'] !== '')    { $where[] = 'h.dong = ?';       $params[] = $f['dong']; }
if ($f['ho'] !== '')      { $where[] = 'h.ho = ?';         $params[] = $f['ho']; }
if ($f['name'] !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM owner ow WHERE ow.household_id = h.id AND ow.name LIKE ?)';
    $params[] = '%' . $f['name'] . '%';
}
$whereSql = implode(' AND ', $where);
$total = (int)(db_row("SELECT COUNT(*) AS n FROM household h WHERE $whereSql", $params)['n'] ?? 0);
$perPage = 30;
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $pages));
$offset = ($page - 1) * $perPage;
$rows = db_all(
    "SELECT h.id, h.dong, h.ho, h.updated_at, c.name AS complex_name
       FROM household h JOIN complex c ON c.id = h.complex_id
      WHERE $whereSql
      ORDER BY c.name, CAST(h.dong AS UNSIGNED), h.dong, CAST(h.ho AS UNSIGNED), h.ho
      LIMIT $perPage OFFSET $offset", $params);
$ids = array_column($rows, 'id');
$ownersBy = [];
if ($ids !== []) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT * FROM owner WHERE household_id IN ($in) ORDER BY is_primary DESC, id", $ids) as $o) {
        $ownersBy[(int)$o['household_id']][] = $o;
    }
}
foreach ($rows as &$r) {
    $r['owners'] = $ownersBy[(int)$r['id']] ?? [];
}
unset($r);

render_admin('member_household', [
    'pageTitle' => '세대/소유자 관리',
    'staff'     => $staff,
    'complexes' => $complexes,
    'rows'      => $rows,
    'total'     => $total,
    'page'      => $page,
    'pages'     => $pages,
    'f'         => $f,
    'edit'      => $edit,
    'showForm'  => $showForm,
    'notice'    => $notice,
    'error'     => $error,
]);
