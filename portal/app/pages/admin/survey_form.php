<?php
/**
 * 설문/등기접수/위임장 등록·수정 — 제목·주최·추가설명 + 문항 빌더.
 * 등기접수는 기간(시작일/종료일)과 핸드폰 확인 필수, 문항 유형 '핸드폰' 추가.
 * 문항 빌더는 q_id[i]/q_type[i]/q_title[i]/q_options[i]/q_required[i] 배열로 온다.
 */

$staff = staff_require();
$surveyType = $surveyType ?? 'survey';
$typeInfo = survey_type_or_fail($surveyType);

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$survey = $id > 0 ? survey_get($id, $surveyType) : null;
if ($id > 0 && $survey === null) {
    http_response_code(404);
    render_admin('error', ['pageTitle' => $typeInfo['label'] . ' 없음', 'staff' => $staff,
        'message' => '해당 ' . $typeInfo['label'] . '을(를) 찾을 수 없습니다. (id=' . $id . ')']);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $title       = trim((string)($_POST['title'] ?? ''));
    $organizer   = trim((string)($_POST['organizer'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $startsOn    = trim((string)($_POST['starts_on'] ?? ''));
    $endsOn      = trim((string)($_POST['ends_on'] ?? ''));
    $phoneReq    = !empty($_POST['phone_required']) ? 1 : 0;
    $isOpen      = !empty($_POST['is_open']) ? 1 : 0;

    if ($title === '') {
        $error = '제목을 넣어 주십시오.';
    } elseif (($startsOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn))
           || ($endsOn !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsOn))) {
        $error = '기간 날짜 형식이 올바르지 않습니다.';
    } elseif ($startsOn !== '' && $endsOn !== '' && $startsOn > $endsOn) {
        $error = '종료일이 시작일보다 빠릅니다.';
    } elseif ($surveyType === 'accept' && ($startsOn === '' || $endsOn === '')) {
        $error = '등기접수는 시작일과 종료일을 모두 넣어 주십시오.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($survey === null) {
                db_exec('INSERT INTO survey (type, title, organizer, description, starts_on, ends_on,
                            phone_required, is_open) VALUES (?,?,?,?,?,?,?,?)',
                    [$surveyType, $title, $organizer, $description !== '' ? $description : null,
                     $startsOn !== '' ? $startsOn : null, $endsOn !== '' ? $endsOn : null,
                     $phoneReq, $isOpen]);
                $id = db_insert_id();
                $action = $typeInfo['label'] . ' 등록';
            } else {
                db_exec('UPDATE survey SET title=?, organizer=?, description=?, starts_on=?, ends_on=?,
                            phone_required=?, is_open=? WHERE id=? AND type=?',
                    [$title, $organizer, $description !== '' ? $description : null,
                     $startsOn !== '' ? $startsOn : null, $endsOn !== '' ? $endsOn : null,
                     $phoneReq, $isOpen, $id, $surveyType]);
                $action = $typeInfo['label'] . ' 수정';
            }
            $qres = survey_save_questions($id, $surveyType, $_POST);
            if (!$qres['ok']) {
                $pdo->rollBack();
                $error = $qres['error'];
                if ($survey === null) {
                    $id = 0;                       // 새 등록 실패 — 입력값 유지 화면으로
                }
            } else {
                $pdo->commit();
                staff_audit((int)$staff['id'], 'survey.save', 'survey:' . $id, "「{$title}」 {$action}");
                redirect($typeInfo['base'] . '?saved=1');
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    // 오류 시 입력값을 그대로 돌려준다
    $survey = [
        'id' => $id, 'type' => $surveyType, 'title' => $title, 'organizer' => $organizer,
        'description' => $description, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
        'phone_required' => $phoneReq, 'is_open' => $isOpen,
    ];
    $questions = [];
    foreach ((array)($_POST['q_type'] ?? []) as $i => $qt) {
        $questions[] = [
            'id'       => (int)(((array)($_POST['q_id'] ?? []))[$i] ?? 0),
            'qtype'    => (string)$qt,
            'title'    => (string)(((array)($_POST['q_title'] ?? []))[$i] ?? ''),
            'options'  => array_values(array_filter(array_map('trim',
                              preg_split('/\R/', (string)(((array)($_POST['q_options'] ?? []))[$i] ?? ''))),
                              fn($o) => $o !== '')),
            'required' => isset(((array)($_POST['q_required'] ?? []))[$i]) ? 1 : 0,
        ];
    }
} else {
    $questions = $survey !== null ? survey_questions($id) : [];
}

render_admin('survey_form', [
    'pageTitle'  => $typeInfo['label'] . ($survey !== null && $id > 0 && $error === '' && $_SERVER['REQUEST_METHOD'] !== 'POST'
                        ? ' 수정' : ' 등록'),
    'staff'      => $staff,
    'surveyType' => $surveyType,
    'typeInfo'   => $typeInfo,
    'survey'     => $survey,
    'questions'  => $questions,
    'error'      => $error,
]);
