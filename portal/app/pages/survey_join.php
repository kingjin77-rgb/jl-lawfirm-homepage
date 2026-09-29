<?php
/**
 * 손님 참여 — 설문/등기접수/위임장 공용 (/participate?id=N).
 * 대상 단지의 세대만 열 수 있고, 진행 중(기간 안)일 때만 제출된다.
 * 세대당 1건 — 다시 제출하면 수정된다.
 */

$customer = customer_require();
$hh = household_context($customer['household_id']);
if ($hh === null) {
    redirect('/logout');
}

$id = (int)($_GET['id'] ?? ($_POST['survey_id'] ?? 0));
$survey = $id > 0 ? db_row('SELECT * FROM survey WHERE id = ?', [$id]) : null;

// 존재하지 않거나 우리 단지 대상이 아니면 홈으로 돌려보낸다.
if ($survey === null
    || !in_array((int)$hh['complex_id'], survey_target_ids($id), true)) {
    redirect('/home');
}

$typeLabels = ['survey' => '설문', 'accept' => '등기접수', 'attorney' => '위임장'];
$typeLabel = $typeLabels[$survey['type']] ?? '설문';
$isOpen = survey_is_open($survey);

$error = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    if (!$isOpen) {
        $error = '지금은 참여 기간이 아닙니다.';   // 기간 밖 제출은 서버에서도 막는다
    } else {
        $res = survey_submit($survey, $customer['household_id'], $customer['owner_name'], $_POST);
        if ($res['ok']) {
            customer_log_action($customer['household_id'], $customer['owner_name'], 'survey.submit:' . $id);
            $saved = true;
        } else {
            $error = $res['error'];
        }
    }
}

$questions = survey_questions($id);
$existing = survey_response_of($id, $customer['household_id']);

render('survey_join', [
    'pageTitle' => $typeLabel . ' 참여 — ' . $survey['title'],
    'customer'  => $customer,
    'hh'        => $hh,
    'survey'    => $survey,
    'typeLabel' => $typeLabel,
    'isOpen'    => $isOpen,
    'questions' => $questions,
    'existing'  => $existing,
    'error'     => $error,
    'saved'     => $saved,
]);
