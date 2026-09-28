<?php
/**
 * FAQ 관리 — 목록 + 등록/수정/노출 전환. 분류는 기존 4개 고정.
 * 손님 /faq 화면이 이 표(faq)를 그대로 읽는다.
 */

$staff = staff_require();

$categories = ['등기', '비용', '서류', '채권환불'];
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $category = (string)($_POST['category'] ?? '');
        $question = trim((string)($_POST['question'] ?? ''));
        $answer   = trim((string)($_POST['answer'] ?? ''));
        $sort     = (int)($_POST['sort'] ?? 0);
        if (!in_array($category, $categories, true)) {
            $error = '분류를 선택해 주십시오.';
        } elseif ($question === '' || $answer === '') {
            $error = '질문과 답변을 모두 넣어 주십시오.';
        } elseif ($id > 0) {
            db_exec('UPDATE faq SET category=?, question=?, answer=?, sort=? WHERE id=?',
                [$category, $question, $answer, $sort, $id]);
            staff_audit((int)$staff['id'], 'faq.update', "faq:$id", mb_substr($question, 0, 80));
            $notice = 'FAQ 를 수정했습니다.';
        } else {
            db_exec('INSERT INTO faq (category, question, answer, sort, visible) VALUES (?,?,?,?,1)',
                [$category, $question, $answer, $sort]);
            staff_audit((int)$staff['id'], 'faq.create', 'faq:' . db_insert_id(), mb_substr($question, 0, 80));
            $notice = 'FAQ 를 등록했습니다.';
        }
    } elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db_exec('UPDATE faq SET visible = 1 - visible WHERE id = ?', [$id]);
        staff_audit((int)$staff['id'], 'faq.toggle', "faq:$id", '노출 전환');
        $notice = '노출 상태를 바꿨습니다.';
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId > 0 ? db_row('SELECT * FROM faq WHERE id = ?', [$editId]) : null;

$faqs = db_all('SELECT * FROM faq ORDER BY category, sort, id');

render_admin('config_faq', [
    'pageTitle'  => 'FAQ 관리',
    'staff'      => $staff,
    'categories' => $categories,
    'faqs'       => $faqs,
    'edit'       => $edit,
    'notice'     => $notice,
    'error'      => $error,
]);
