<?php
/**
 * 설문·등기접수·위임장 공용 엔진 — 세 메뉴가 survey.type 하나로 같은
 * 표(survey/survey_target/survey_question/survey_response/survey_answer)를 쓴다.
 * 관리자(app/pages/admin/survey_*.php)와 손님 참여(app/pages/survey_join.php)가
 * 함께 부른다.
 */

/** 유형별 라벨·경로 — 메뉴와 화면 문구가 여기서 나온다. */
function survey_types(): array
{
    return [
        'survey'   => ['label' => '설문',     'base' => '/admin/survey',   'noun' => '설문'],
        'accept'   => ['label' => '등기접수', 'base' => '/admin/accept',   'noun' => '접수'],
        'attorney' => ['label' => '위임장',   'base' => '/admin/attorney', 'noun' => '위임장'],
    ];
}

/** 문항 유형 라벨 — 핸드폰 문항은 등기접수에서만 만들 수 있다. */
function survey_qtype_labels(string $type): array
{
    $labels = ['choice' => '객관식', 'text' => '주관식', 'note' => '설명글'];
    if ($type === 'accept') {
        $labels['phone'] = '핸드폰';
    }
    return $labels;
}

/** 유형 검증 — 라우터가 넘긴 값이 아니면 즉시 404. */
function survey_type_or_fail(string $type): array
{
    $types = survey_types();
    if (!isset($types[$type])) {
        http_response_code(404);
        exit('알 수 없는 유형입니다.');
    }
    return $types[$type];
}

/** 설문 1건 — 유형까지 맞아야 준다 (다른 메뉴의 id 를 섞어 쓰지 못하게). */
function survey_get(int $id, string $type): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db_row('SELECT * FROM survey WHERE id = ? AND type = ?', [$id, $type]);
}

/** 문항 목록 (보기 JSON 을 배열로 풀어서) */
function survey_questions(int $surveyId): array
{
    $rows = db_all('SELECT * FROM survey_question WHERE survey_id = ? ORDER BY sort, id', [$surveyId]);
    foreach ($rows as &$q) {
        $opts = json_decode((string)($q['options_json'] ?? ''), true);
        $q['options'] = is_array($opts) ? array_values(array_filter($opts, fn($o) => trim((string)$o) !== '')) : [];
    }
    unset($q);
    return $rows;
}

/** 참여대상 단지 id 목록 */
function survey_target_ids(int $surveyId): array
{
    return array_map('intval', array_column(
        db_all('SELECT complex_id FROM survey_target WHERE survey_id = ?', [$surveyId]), 'complex_id'));
}

/**
 * 목록용 집계 — 총인원(대상 단지 세대수)/참여/불참을 설문별로 붙인다.
 * @param array $surveys survey 행 배열 (id 필수)
 */
function survey_attach_counts(array $surveys): array
{
    foreach ($surveys as &$s) {
        $sid = (int)$s['id'];
        $total = (int)(db_row(
            'SELECT COUNT(*) AS n FROM household h
              WHERE h.complex_id IN (SELECT complex_id FROM survey_target WHERE survey_id = ?)',
            [$sid])['n'] ?? 0);
        $joined = (int)(db_row('SELECT COUNT(*) AS n FROM survey_response WHERE survey_id = ?', [$sid])['n'] ?? 0);
        $s['cnt_total']  = $total;
        $s['cnt_joined'] = $joined;
        $s['cnt_absent'] = max(0, $total - $joined);
    }
    unset($s);
    return $surveys;
}

/**
 * 진행 중 판정 — 등기접수는 기간(시작일~종료일, 양끝 포함)으로,
 * 설문·위임장은 is_open 스위치로 본다. 기간이 있는 설문/위임장도 기간을 따른다.
 */
function survey_is_open(array $s, ?string $today = null): bool
{
    $today = $today ?? date('Y-m-d');
    if (!empty($s['starts_on']) && $today < $s['starts_on']) {
        return false;
    }
    if (!empty($s['ends_on']) && $today > $s['ends_on']) {
        return false;
    }
    if ($s['type'] === 'accept') {
        return !empty($s['starts_on']) || !empty($s['ends_on']) ? true : (bool)$s['is_open'];
    }
    return (bool)$s['is_open'];
}

/** 손님 홈 카드 — 이 세대의 단지를 대상으로 한 진행 중 건 전부. */
function survey_open_for_complex(int $complexId): array
{
    $rows = db_all(
        'SELECT s.* FROM survey s
           JOIN survey_target t ON t.survey_id = s.id
          WHERE t.complex_id = ?
          ORDER BY s.id DESC', [$complexId]);
    return array_values(array_filter($rows, fn($s) => survey_is_open($s)));
}

/** 핸드폰 형식 — 01x-xxxx-xxxx (붙임표 유무 허용) */
function survey_phone_valid(string $phone): bool
{
    return (bool)preg_match('/^01[016789]-?\d{3,4}-?\d{4}$/', trim($phone));
}

/**
 * 문항 저장 — 폼 배열(q_id/q_type/q_title/q_options/q_required)을 받아
 * 기존 문항은 갱신, 새 문항은 추가, 빠진 문항은 삭제한다 (답은 CASCADE).
 * @return array{ok:bool, error:string}
 */
function survey_save_questions(int $surveyId, string $type, array $post): array
{
    $ids      = (array)($post['q_id'] ?? []);
    $qtypes   = (array)($post['q_type'] ?? []);
    $titles   = (array)($post['q_title'] ?? []);
    $options  = (array)($post['q_options'] ?? []);
    $required = (array)($post['q_required'] ?? []);   // 체크된 행 번호 키

    $allowed = array_keys(survey_qtype_labels($type));
    $rows = [];
    foreach ($qtypes as $i => $qt) {
        $title = trim((string)($titles[$i] ?? ''));
        $qt = (string)$qt;
        if ($title === '') {
            continue;                                  // 제목 없는 행은 버린다
        }
        if (!in_array($qt, $allowed, true)) {
            return ['ok' => false, 'error' => '허용되지 않은 문항 유형입니다.'];
        }
        $opts = [];
        if ($qt === 'choice') {
            foreach (preg_split('/\R/', (string)($options[$i] ?? '')) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $opts[] = $line;
                }
            }
            if (count($opts) < 2) {
                return ['ok' => false, 'error' => "객관식 「{$title}」 의 보기를 2개 이상 넣어 주십시오 (한 줄에 하나)."];
            }
        }
        $rows[] = [
            'id'       => (int)($ids[$i] ?? 0),
            'qtype'    => $qt,
            'title'    => $title,
            'options'  => $opts,
            'required' => isset($required[$i]) ? 1 : 0,
        ];
    }
    if ($rows === []) {
        return ['ok' => false, 'error' => '문항을 1개 이상 넣어 주십시오.'];
    }

    $keep = [];
    foreach ($rows as $sort => $q) {
        $optJson = $q['qtype'] === 'choice' ? json_encode($q['options'], JSON_UNESCAPED_UNICODE) : null;
        $req = $q['qtype'] === 'note' ? 0 : $q['required'];
        if ($q['id'] > 0 && db_row('SELECT id FROM survey_question WHERE id = ? AND survey_id = ?', [$q['id'], $surveyId]) !== null) {
            db_exec('UPDATE survey_question SET qtype=?, title=?, options_json=?, required=?, sort=? WHERE id=?',
                [$q['qtype'], $q['title'], $optJson, $req, $sort, $q['id']]);
            $keep[] = $q['id'];
        } else {
            db_exec('INSERT INTO survey_question (survey_id, qtype, title, options_json, required, sort)
                     VALUES (?,?,?,?,?,?)', [$surveyId, $q['qtype'], $q['title'], $optJson, $req, $sort]);
            $keep[] = db_insert_id();
        }
    }
    $in = implode(',', array_fill(0, count($keep), '?'));
    db_exec("DELETE FROM survey_question WHERE survey_id = ? AND id NOT IN ($in)",
        array_merge([$surveyId], $keep));
    return ['ok' => true, 'error' => ''];
}

/**
 * 손님 제출 검증 + 저장 — 세대당 1건 업서트(다시 제출하면 수정).
 * @return array{ok:bool, error:string}
 */
function survey_submit(array $survey, int $householdId, string $ownerName, array $post): array
{
    $questions = survey_questions((int)$survey['id']);
    $answers = [];
    foreach ($questions as $q) {
        if ($q['qtype'] === 'note') {
            continue;
        }
        $qid = (int)$q['id'];
        $val = trim((string)($post['q' . $qid] ?? ''));
        if ($val === '') {
            if ($q['required']) {
                return ['ok' => false, 'error' => '「' . $q['title'] . '」 항목은 꼭 답해 주셔야 합니다.'];
            }
            continue;
        }
        if ($q['qtype'] === 'choice' && !in_array($val, $q['options'], true)) {
            return ['ok' => false, 'error' => '「' . $q['title'] . '」 의 보기에서 골라 주십시오.'];
        }
        if ($q['qtype'] === 'phone' && !survey_phone_valid($val)) {
            return ['ok' => false, 'error' => '「' . $q['title'] . '」 의 핸드폰 번호 형식이 올바르지 않습니다. (예: 010-1234-5678)'];
        }
        if (mb_strlen($val) > 1000) {
            return ['ok' => false, 'error' => '「' . $q['title'] . '」 답이 너무 깁니다 (1,000자 이하).'];
        }
        $answers[$qid] = $val;
    }

    $phone = trim((string)($post['response_phone'] ?? ''));
    if (!empty($survey['phone_required'])) {
        if (!survey_phone_valid($phone)) {
            return ['ok' => false, 'error' => '핸드폰 번호를 형식에 맞게 넣어 주십시오. (예: 010-1234-5678)'];
        }
    } else {
        $phone = '';
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_exec('INSERT INTO survey_response (survey_id, household_id, owner_name, phone)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE owner_name = VALUES(owner_name), phone = VALUES(phone),
                                         submitted_at = CURRENT_TIMESTAMP',
            [(int)$survey['id'], $householdId, $ownerName, $phone]);
        $resp = db_row('SELECT id FROM survey_response WHERE survey_id = ? AND household_id = ?',
            [(int)$survey['id'], $householdId]);
        $rid = (int)$resp['id'];
        db_exec('DELETE FROM survey_answer WHERE response_id = ?', [$rid]);
        foreach ($answers as $qid => $val) {
            db_exec('INSERT INTO survey_answer (response_id, question_id, value) VALUES (?,?,?)',
                [$rid, $qid, $val]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'error' => ''];
}

/** 세대의 기존 응답 (없으면 null) — 답을 question_id => value 로 붙인다. */
function survey_response_of(int $surveyId, int $householdId): ?array
{
    $resp = db_row('SELECT * FROM survey_response WHERE survey_id = ? AND household_id = ?',
        [$surveyId, $householdId]);
    if ($resp === null) {
        return null;
    }
    $resp['answers'] = [];
    foreach (db_all('SELECT question_id, value FROM survey_answer WHERE response_id = ?', [(int)$resp['id']]) as $a) {
        $resp['answers'][(int)$a['question_id']] = $a['value'];
    }
    return $resp;
}

/**
 * 참여/불참 데이타 — 참여는 응답+답, 불참은 대상 단지 세대 중 미응답.
 * @param string $mode 'joined' | 'absent'
 */
function survey_data_rows(int $surveyId, string $mode): array
{
    if ($mode === 'absent') {
        $rows = db_all(
            'SELECT h.id AS household_id, c.name AS complex_name, h.dong, h.ho
               FROM household h JOIN complex c ON c.id = h.complex_id
              WHERE h.complex_id IN (SELECT complex_id FROM survey_target WHERE survey_id = ?)
                AND NOT EXISTS (SELECT 1 FROM survey_response r
                                 WHERE r.survey_id = ? AND r.household_id = h.id)
              ORDER BY c.name, CAST(h.dong AS UNSIGNED), h.dong, CAST(h.ho AS UNSIGNED), h.ho',
            [$surveyId, $surveyId]);
    } else {
        $rows = db_all(
            'SELECT r.id AS response_id, r.owner_name, r.phone, r.submitted_at,
                    h.id AS household_id, c.name AS complex_name, h.dong, h.ho
               FROM survey_response r
               JOIN household h ON h.id = r.household_id
               JOIN complex c ON c.id = h.complex_id
              WHERE r.survey_id = ?
              ORDER BY c.name, CAST(h.dong AS UNSIGNED), h.dong, CAST(h.ho AS UNSIGNED), h.ho',
            [$surveyId]);
    }
    // 명의인 이름 붙이기 (불참 목록·참여 목록 공용)
    $ids = array_column($rows, 'household_id');
    $ownersBy = [];
    if ($ids !== []) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (db_all("SELECT household_id, name FROM owner WHERE household_id IN ($in)
                          ORDER BY is_primary DESC, id", $ids) as $o) {
            $ownersBy[(int)$o['household_id']][] = $o['name'];
        }
    }
    foreach ($rows as &$r) {
        $r['owner_names'] = implode(' · ', $ownersBy[(int)$r['household_id']] ?? []);
    }
    unset($r);
    return $rows;
}

/** 참여 데이타에 답 컬럼 붙이기 — response_id => [question_id => value] */
function survey_answers_by_response(int $surveyId): array
{
    $out = [];
    foreach (db_all(
        'SELECT a.response_id, a.question_id, a.value
           FROM survey_answer a JOIN survey_response r ON r.id = a.response_id
          WHERE r.survey_id = ?', [$surveyId]) as $a) {
        $out[(int)$a['response_id']][(int)$a['question_id']] = $a['value'];
    }
    return $out;
}
