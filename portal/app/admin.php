<?php
/**
 * 직원 관리자 공용 — 세션 보호, 메뉴 구성, 레이아웃 렌더, 목록 필터,
 * 민감정보 마스킹, 설정 읽기/쓰기, 엑셀 가져오기(미리보기·확정 공용).
 * 직원 세션($_SESSION['staff'])은 손님 세션($_SESSION['customer'])과 키가 달라
 * 서로 간섭하지 않는다.
 */

require_once __DIR__ . '/xlsx.php';

/* ------------------------------------------------------------------ */
/* 세션 보호                                                            */
/* ------------------------------------------------------------------ */

/** 직원 세션 필수 — 없으면 /admin/login 으로 (손님 세션이어도 마찬가지). */
function staff_require(): array
{
    $s = staff_current();
    if ($s === null) {
        redirect('/admin/login');
    }
    return $s;
}

/** admin 권한 필수 — staff 권한이면 403. */
function staff_require_admin(): array
{
    $s = staff_require();
    if (($s['role'] ?? '') !== 'admin') {
        http_response_code(403);
        render_admin('error', [
            'pageTitle' => '권한 없음',
            'staff'     => $s,
            'message'   => '이 화면은 admin 권한 직원만 열 수 있습니다.',
        ]);
        exit;
    }
    return $s;
}

/* ------------------------------------------------------------------ */
/* 메뉴 — 상단 7메뉴 + 좌측 서브메뉴 (기존 관리자 구조 그대로)            */
/* ------------------------------------------------------------------ */

function admin_menus(): array
{
    return [
        'config' => ['label' => '기본 정보 관리', 'home' => '/admin/config', 'sub' => [
            '/admin/config'        => '기본 정보 설정',
            '/admin/config/ops'    => '운영 정보 설정',
            '/admin/config/popup'  => '팝업 관리',
            '/admin/config/faq'    => 'FAQ 관리',
            '/admin/config/staff'  => '직원 계정 관리',
        ]],
        'member' => ['label' => '인적사항 관리', 'home' => '/admin/member/complex', 'sub' => [
            '/admin/member/complex'   => '아파트 관리',
            '/admin/member/household' => '세대/소유자 관리',
            '/admin/member/sms'       => 'SMS 발송',
            '/admin/member/sms-log'   => 'SMS 발송 내역',
        ]],
        'registration' => ['label' => '등기진행 관리', 'home' => '/admin/registration', 'sub' => [
            '/admin/registration'         => '등기진행 현황',
            '/admin/registration/cost'    => '등기비용 현황',
            '/admin/registration/address' => '권리증수령주소 현황',
            '/admin/registration/refund'  => '채권환불 신청현황',
            '/admin/registration/upload'  => '등기진행 등록 (엑셀)',
        ]],
        'survey' => ['label' => '설문 관리', 'home' => '/admin/survey', 'sub' => [
            '/admin/survey'      => '설문 목록',
            '/admin/survey/form' => '설문 등록',
            '/admin/survey/sms'  => '참여자 SMS',
        ]],
        'accept' => ['label' => '등기접수 관리', 'home' => '/admin/accept', 'sub' => [
            '/admin/accept'      => '등기접수 목록',
            '/admin/accept/form' => '등기접수 등록',
            '/admin/accept/sms'  => '참여자 SMS',
        ]],
        'attorney' => ['label' => '위임장 관리', 'home' => '/admin/attorney', 'sub' => [
            '/admin/attorney'      => '위임장 목록',
            '/admin/attorney/form' => '위임장 등록',
            '/admin/attorney/sms'  => '참여자 SMS',
        ]],
        'stats' => ['label' => '접속 통계', 'home' => '/admin/stats', 'sub' => [
            '/admin/stats'         => '일자별 손님 로그인',
            '/admin/stats/lockout' => '조회 실패·잠금',
            '/admin/stats/audit'   => '직원 작업기록',
        ]],
    ];
}

/** 현재 경로가 속한 상단 메뉴 키 */
function admin_active_section(string $path): string
{
    if ($path === '/admin' || $path === '/admin/') {
        return '';
    }
    foreach (admin_menus() as $key => $menu) {
        foreach ($menu['sub'] as $href => $label) {
            if ($path === $href || str_starts_with($path, $href . '/')) {
                return $key;
            }
        }
        if (str_starts_with($path, '/admin/' . $key)) {
            return $key;
        }
    }
    return '';
}

/** 관리자 뷰 렌더 — app/views/admin/<name>.php 를 admin_layout 으로 감싼다. */
function render_admin(string $view, array $data = []): void
{
    $viewFile = __DIR__ . '/views/admin/' . basename($view) . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit('admin view not found: ' . h($view));
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require __DIR__ . '/views/admin_layout.php';
}

/* ------------------------------------------------------------------ */
/* 설정 (setting 키-값)                                                 */
/* ------------------------------------------------------------------ */

function setting_get_all(): array
{
    $out = [];
    foreach (db_all('SELECT name, value FROM setting') as $row) {
        $out[$row['name']] = $row['value'];
    }
    return $out;
}

function setting_set(string $name, string $value): void
{
    db_exec('INSERT INTO setting (name, value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
}

/** 기본 정보 설정 항목 — 키 ↔ 라벨 (기존 화면 항목 그대로) */
function firm_setting_fields(): array
{
    return [
        'firm_name'        => '상호',
        'firm_ceo'         => '대표자',
        'firm_biz_no'      => '사업자등록번호',
        'firm_tel'         => '대표전화',
        'firm_fax'         => '팩스',
        'firm_email'       => '이메일',
        'privacy_name'     => '개인정보보호책임자 성명',
        'privacy_position' => '개인정보보호책임자 직책',
        'privacy_email'    => '개인정보보호책임자 이메일',
        'firm_address'     => '주소',
    ];
}

/* ------------------------------------------------------------------ */
/* 마스킹 — 대시보드 미리보기용 (전체 값은 전용 현황 화면에서만)          */
/* ------------------------------------------------------------------ */

/** 주소 앞 10자 + … */
function mask_address(string $addr): string
{
    return mb_strlen($addr) > 10 ? mb_substr($addr, 0, 10) . '…' : $addr;
}

/** 계좌 → 은행명 + 뒤 3자리 */
function mask_account(string $bank, string $account): string
{
    $digits = preg_replace('/\D/', '', $account);
    $tail = strlen($digits) >= 3 ? substr($digits, -3) : $digits;
    return $bank . ' …' . $tail;
}

/* ------------------------------------------------------------------ */
/* 등기진행 목록 필터 — 현황/비용/주소/환불/내보내기가 같이 쓴다          */
/* ------------------------------------------------------------------ */

/** GET 에서 필터를 읽는다. steps 는 체크된 단계번호 배열. */
function reg_filters_from_get(): array
{
    $steps = [];
    foreach ((array)($_GET['steps'] ?? []) as $s) {
        $n = (int)$s;
        if ($n >= 1 && $n <= 8) {
            $steps[] = $n;
        }
    }
    return [
        'complex_id' => (int)($_GET['complex_id'] ?? 0),
        'dong'       => trim((string)($_GET['dong'] ?? '')),
        'ho'         => trim((string)($_GET['ho'] ?? '')),
        'name'       => trim((string)($_GET['name'] ?? '')),
        'birth6'     => preg_replace('/\D/', '', (string)($_GET['birth6'] ?? '')),
        'steps'      => array_values(array_unique($steps)),
    ];
}

/**
 * 필터 → WHERE 절 + 파라미터. household h / complex c / progress p 별칭 기준.
 * 이름·생년월일은 소유자 EXISTS 로 건다. 단계 체크는 전부 완료(AND) 조건.
 */
function reg_filters_where(array $f): array
{
    $where = ['1=1'];
    $params = [];
    if ($f['complex_id'] > 0) {
        $where[] = 'h.complex_id = ?';
        $params[] = $f['complex_id'];
    }
    if ($f['dong'] !== '') {
        $where[] = 'h.dong = ?';
        $params[] = $f['dong'];
    }
    if ($f['ho'] !== '') {
        $where[] = 'h.ho = ?';
        $params[] = $f['ho'];
    }
    if ($f['name'] !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM owner ow WHERE ow.household_id = h.id AND ow.name LIKE ?)';
        $params[] = '%' . $f['name'] . '%';
    }
    if ($f['birth6'] !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM owner ow2 WHERE ow2.household_id = h.id AND ow2.birth6 = ?)';
        $params[] = $f['birth6'];
    }
    foreach ($f['steps'] as $n) {
        $where[] = "(p.step{$n}_date IS NOT NULL OR p.step{$n}_done = 1)";
    }
    return [implode(' AND ', $where), $params];
}

/** 필터 hidden/쿼리스트링 재구성 (페이지 이동·내보내기 링크용) */
function reg_filters_query(array $f, array $extra = []): string
{
    $q = array_filter([
        'complex_id' => $f['complex_id'] ?: null,
        'dong'       => $f['dong'] !== '' ? $f['dong'] : null,
        'ho'         => $f['ho'] !== '' ? $f['ho'] : null,
        'name'       => $f['name'] !== '' ? $f['name'] : null,
        'birth6'     => $f['birth6'] !== '' ? $f['birth6'] : null,
    ], fn($v) => $v !== null);
    foreach ($f['steps'] as $i => $n) {
        $q["steps[$i]"] = $n;
    }
    return http_build_query(array_merge($q, $extra));
}

/** 세대 목록 한 페이지 (30건) + 총건수. 소유자들을 붙여서 돌려준다. */
function reg_fetch_page(array $f, int $page, int $perPage = 30): array
{
    [$whereSql, $params] = reg_filters_where($f);
    $base = 'FROM household h
             JOIN complex c ON c.id = h.complex_id
             LEFT JOIN progress p ON p.household_id = h.id
             LEFT JOIN cost k ON k.household_id = h.id
             WHERE ' . $whereSql;
    $total = (int)(db_row("SELECT COUNT(*) AS n $base", $params)['n'] ?? 0);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    // LIMIT/OFFSET 은 정수로 검증해 직접 넣는다 (PDO 네이티브 프리페어의 LIMIT 자리표시 제약 회피).
    $rows = db_all(
        "SELECT h.id, h.dong, h.ho, c.name AS complex_name, p.*, k.total, k.paid_amount, k.diff,
                k.acq_tax, k.bond_transfer, k.bond_mortgage, k.stamp_tax, k.cert_stamp, k.via_stamp,
                k.trust_cancel, k.cert_fees, k.fee, k.vat, k.etc_amt, k.paid_date
           $base ORDER BY c.name, CAST(h.dong AS UNSIGNED), h.dong, CAST(h.ho AS UNSIGNED), h.ho
           LIMIT $perPage OFFSET $offset", $params);
    // 소유자 붙이기
    $ids = array_column($rows, 'id');
    $ownersBy = [];
    if ($ids !== []) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (db_all("SELECT household_id, name, birth6, phone, is_primary
                           FROM owner WHERE household_id IN ($in)
                          ORDER BY is_primary DESC, id", $ids) as $o) {
            $ownersBy[(int)$o['household_id']][] = $o;
        }
    }
    foreach ($rows as &$r) {
        $r['owners'] = $ownersBy[(int)$r['id']] ?? [];
    }
    unset($r);
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/* ------------------------------------------------------------------ */
/* 등기진행 엑셀 가져오기 — 미리보기와 확정이 같은 파서를 쓴다            */
/* ------------------------------------------------------------------ */

/** 생년월일 6자리 정규화 — 숫자만 남기고, 앞자리 0 이 떨어진 값은 채운다. */
function import_birth6(string $raw): ?string
{
    $d = preg_replace('/\D/', '', $raw);
    if ($d === '') {
        return null;
    }
    if (strlen($d) < 6) {
        $d = str_pad($d, 6, '0', STR_PAD_LEFT);   // 010203 → 엑셀이 10203 으로 준 경우
    } elseif (strlen($d) > 6) {
        $d = substr($d, 0, 6);                    // 주민번호 전체가 든 경우 앞 6자리
    }
    return $d;
}

/** 금액 칸 → 원 단위 정수. 빈칸/공백은 0. 숫자가 아니면 null(오류). */
function import_amount(?string $raw): ?int
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return 0;
    }
    $clean = str_replace([',', ' ', '원'], '', $raw);
    if (!is_numeric($clean)) {
        return null;
    }
    return (int)round((float)$clean);
}

/** 날짜 칸 → 'Y-m-d' | null. 엑셀 직렬값·문자열 날짜 모두 처리. 0/빈칸은 null. */
function import_date(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0') {
        return null;
    }
    if (is_numeric($raw)) {
        return xlsx_serial_to_date((float)$raw);
    }
    $norm = str_replace(['.', '/'], '-', $raw);
    $ts = strtotime($norm);
    return $ts === false ? null : date('Y-m-d', $ts);
}

/** 텍스트 칸 — 기존 엑셀은 빈 값을 0 으로 채워 두므로 0 은 빈 것으로 본다. */
function import_text(?string $raw): string
{
    $raw = trim((string)$raw);
    return ($raw === '0') ? '' : $raw;
}

/**
 * 엑셀 → 구조화 행. 머리글 행(1열이 '순번')을 찾아 그 다음 행부터 읽는다.
 * SPEC 열 배치: 2동 3호 4~5성명 6~7주민번호 8~15단계 16~26비용
 * 32~33합계 34입금일 35입금액 36차액 37미비서류 38발송일 39주소 40은행 41계좌 42~43핸드폰
 * @return array{ok:bool, error:string, rows:array, errors:array}
 */
function import_parse_xlsx(string $path): array
{
    $res = xlsx_read_rows($path);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'], 'rows' => [], 'errors' => []];
    }
    $sheet = $res['rows'];
    ksort($sheet);

    // 머리글 행 탐지 — 1열 '순번' (기존 샘플은 4행, SPEC 문서는 3행이라 자동 탐지)
    $headerRow = 0;
    foreach ($sheet as $rn => $cells) {
        if (trim((string)($cells[1] ?? '')) === '순번') {
            $headerRow = $rn;
            break;
        }
        if ($rn > 10) {
            break;
        }
    }
    if ($headerRow === 0) {
        return ['ok' => false, 'errors' => [], 'rows' => [],
            'error' => "머리글 행(1열 '순번')을 찾지 못했습니다. 기존 등기진행 엑셀 양식인지 확인해 주십시오."];
    }

    $out = [];
    $errors = [];
    foreach ($sheet as $rn => $cells) {
        if ($rn <= $headerRow) {
            continue;
        }
        $dong = trim((string)($cells[2] ?? ''));
        $ho   = trim((string)($cells[3] ?? ''));
        $name1 = trim((string)($cells[4] ?? ''));
        if ($dong === '' && $ho === '' && $name1 === '') {
            continue;                                   // 완전 빈 행은 건너뜀
        }
        $err = [];
        if ($dong === '' || $ho === '') {
            $err[] = '동/호가 비어 있습니다';
        }
        if ($name1 === '') {
            $err[] = '성명(4열)이 비어 있습니다';
        }

        // 소유자 1~2 (공동명의는 옆 칸)
        $owners = [];
        foreach ([0 => [4, 6, 42], 1 => [5, 7, 43]] as $i => [$cName, $cBirth, $cPhone]) {
            $nm = trim((string)($cells[$cName] ?? ''));
            if ($nm === '' || $nm === '0') {
                continue;
            }
            $b6 = import_birth6((string)($cells[$cBirth] ?? ''));
            if ($b6 === null) {
                $err[] = ($i === 0 ? '주민번호(6열)' : '공동명의 주민번호(7열)') . '가 비어 있거나 숫자가 아닙니다';
                $b6 = '';
            }
            $owners[] = [
                'name'   => $nm,
                'birth6' => $b6,
                'phone'  => import_text((string)($cells[$cPhone] ?? '')),
            ];
        }

        // 진행 8단계 — '완료' 표기 또는 날짜
        $steps = [];
        for ($n = 1; $n <= 8; $n++) {
            $raw = trim((string)($cells[7 + $n] ?? ''));
            $done = false;
            $date = null;
            if ($raw !== '' && $raw !== '0') {
                if (mb_strpos($raw, '완료') !== false) {
                    $done = true;
                } else {
                    $date = import_date($raw);
                    $done = $date !== null;
                }
            }
            $steps[$n] = ['done' => $done, 'date' => $date];
        }

        // 비용 11항목 (16~26)
        $cost = [];
        $costCols = array_keys(cost_item_labels());       // 순서 = 16..26
        foreach ($costCols as $i => $key) {
            $amt = import_amount($cells[16 + $i] ?? null);
            if ($amt === null) {
                $err[] = cost_item_labels()[$key] . '(' . (16 + $i) . '열) 금액이 숫자가 아닙니다';
                $amt = 0;
            }
            $cost[$key] = $amt;
        }
        $total = import_amount($cells[32] ?? ($cells[33] ?? null));
        if ($total === null) {
            $err[] = '등기비용합계(32열)가 숫자가 아닙니다';
            $total = 0;
        }
        if ($total === 0) {
            $total = array_sum($cost);
        }
        $paid = import_amount($cells[35] ?? null);
        if ($paid === null) {
            $err[] = '입금액(35열)이 숫자가 아닙니다';
            $paid = 0;
        }

        $row = [
            'excel_row' => $rn,
            'dong'      => $dong,
            'ho'        => $ho,
            'owners'    => $owners,
            'steps'     => $steps,
            'cost'      => $cost,
            'total'     => $total,
            'paid_date' => import_date($cells[34] ?? null),
            'paid'      => $paid,
            'diff'      => $paid - $total,                // 차액 = 입금액 - 합계
            'missing'   => import_text((string)($cells[37] ?? '')),
            'sent_date' => import_date($cells[38] ?? null),   // 38열 권리증 발송일
            'addr'      => import_text((string)($cells[39] ?? '')),
            'bank'      => import_text((string)($cells[40] ?? '')),
            'account'   => import_text((string)($cells[41] ?? '')),
        ];
        if ($err !== []) {
            $errors[] = ['excel_row' => $rn, 'dong' => $dong, 'ho' => $ho, 'messages' => $err];
        }
        $out[] = $row;
    }
    if ($out === []) {
        return ['ok' => false, 'error' => '자료 행이 없습니다.', 'rows' => [], 'errors' => $errors];
    }
    return ['ok' => true, 'error' => '', 'rows' => $out, 'errors' => $errors];
}

/**
 * 확정 반입 — (단지,동,호) 업서트. 트랜잭션 하나로 묶어 실패 시 전부 되돌린다.
 * @return array{created:int, updated:int}
 */
function import_apply(int $complexId, array $rows): array
{
    $created = 0;
    $updated = 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $r) {
            $hh = db_row('SELECT id FROM household WHERE complex_id = ? AND dong = ? AND ho = ?',
                [$complexId, $r['dong'], $r['ho']]);
            if ($hh === null) {
                db_exec('INSERT INTO household (complex_id, dong, ho) VALUES (?,?,?)',
                    [$complexId, $r['dong'], $r['ho']]);
                $hid = db_insert_id();
                $created++;
            } else {
                $hid = (int)$hh['id'];
                $updated++;
            }

            // 소유자 — 엑셀이 최신 원장이므로 그 세대의 명의인을 통째로 교체한다.
            db_exec('DELETE FROM owner WHERE household_id = ?', [$hid]);
            foreach ($r['owners'] as $i => $o) {
                db_exec('INSERT INTO owner (household_id, name, birth6, phone, is_primary) VALUES (?,?,?,?,?)',
                    [$hid, $o['name'], $o['birth6'], $o['phone'], $i === 0 ? 1 : 0]);
            }

            // 진행 — done/date 8단계 + 미비서류
            $cols = ['household_id' => $hid];
            for ($n = 1; $n <= 8; $n++) {
                $cols["step{$n}_date"] = $r['steps'][$n]['date'];
                $cols["step{$n}_done"] = $r['steps'][$n]['done'] ? 1 : 0;
            }
            $cols['missing_docs'] = $r['missing'] !== '' ? $r['missing'] : null;
            $cols['sent_date']    = $r['sent_date'];          // 38열 발송일 (없으면 NULL)
            $names = array_keys($cols);
            $set = implode(', ', array_map(fn($c) => "$c = VALUES($c)", array_slice($names, 1)));
            db_exec('INSERT INTO progress (' . implode(',', $names) . ') VALUES ('
                . implode(',', array_fill(0, count($names), '?')) . ")
                ON DUPLICATE KEY UPDATE $set", array_values($cols));

            // 비용
            $c = $r['cost'];
            db_exec('INSERT INTO cost (household_id, acq_tax, bond_transfer, bond_mortgage, stamp_tax,
                        cert_stamp, via_stamp, trust_cancel, cert_fees, fee, vat, etc_amt,
                        total, paid_date, paid_amount, diff)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE acq_tax=VALUES(acq_tax), bond_transfer=VALUES(bond_transfer),
                        bond_mortgage=VALUES(bond_mortgage), stamp_tax=VALUES(stamp_tax),
                        cert_stamp=VALUES(cert_stamp), via_stamp=VALUES(via_stamp),
                        trust_cancel=VALUES(trust_cancel), cert_fees=VALUES(cert_fees),
                        fee=VALUES(fee), vat=VALUES(vat), etc_amt=VALUES(etc_amt),
                        total=VALUES(total), paid_date=VALUES(paid_date),
                        paid_amount=VALUES(paid_amount), diff=VALUES(diff)',
                [$hid, $c['acq_tax'], $c['bond_transfer'], $c['bond_mortgage'], $c['stamp_tax'],
                 $c['cert_stamp'], $c['via_stamp'], $c['trust_cancel'], $c['cert_fees'],
                 $c['fee'], $c['vat'], $c['etc_amt'],
                 $r['total'], $r['paid_date'], $r['paid'], $r['diff']]);

            // 권리증 발송주소(39열) — 최신 등록분과 다를 때만 새 행 (암호화 저장)
            if ($r['addr'] !== '') {
                $latest = db_row('SELECT addr1_enc FROM address_request WHERE household_id = ?
                                  ORDER BY id DESC LIMIT 1', [$hid]);
                $latestPlain = $latest !== null ? decrypt_field($latest['addr1_enc']) : null;
                if ($latestPlain !== $r['addr']) {
                    db_exec('INSERT INTO address_request (household_id, zip, addr1_enc, addr2_enc)
                             VALUES (?,?,?,?)', [$hid, '', encrypt_field($r['addr']), '']);
                }
            }
            // 채권환불 은행/계좌(40~41열) — 예금주는 대표 명의인으로
            if ($r['bank'] !== '' && $r['account'] !== '') {
                $holder = $r['owners'][0]['name'] ?? '';
                $latest = db_row('SELECT bank, account_enc FROM refund_request WHERE household_id = ?
                                  ORDER BY id DESC LIMIT 1', [$hid]);
                $latestPlain = $latest !== null ? decrypt_field($latest['account_enc']) : null;
                if ($latest === null || $latest['bank'] !== $r['bank'] || $latestPlain !== $r['account']) {
                    db_exec('INSERT INTO refund_request (household_id, bank, account_enc, holder)
                             VALUES (?,?,?,?)', [$hid, $r['bank'], encrypt_field($r['account']), $holder]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['created' => $created, 'updated' => $updated];
}

/* ------------------------------------------------------------------ */
/* SMS — 발송 업체 연동 전이라 대기 저장만 한다                          */
/* ------------------------------------------------------------------ */

const SMS_SENDER = '1899-4252';

/** 한글 2바이트 기준(EUC-KR) 바이트 수 — 기존 시스템의 90/2000 기준과 동일 */
function sms_byte_length(string $msg): int
{
    $conv = @iconv('UTF-8', 'EUC-KR//IGNORE', $msg);
    if ($conv !== false) {
        return strlen($conv);
    }
    // iconv 미지원 폴백: ASCII 1바이트, 그 외 2바이트
    $bytes = 0;
    foreach (preg_split('//u', $msg, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $bytes += strlen($ch) === 1 ? 1 : 2;
    }
    return $bytes;
}
