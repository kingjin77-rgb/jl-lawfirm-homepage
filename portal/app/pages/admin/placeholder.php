<?php
/**
 * 3단계 자리표시 — 설문 관리 · 등기접수 관리 · 위임장 관리.
 * 기존 화면의 하위 구성만 안내하고 엔진은 3단계에서 만든다.
 */

$staff = staff_require();

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$sections = [
    '/admin/survey' => [
        'title' => '설문 관리',
        'items' => [
            '설문 목록 — 설문명 · 주최 · 총인원/참여/불참 · 작성일',
            '설문 등록 — 설문제목 · 주최 · 배너이미지 · 추가설명 · 문항 빌더(객관식/주관식)',
            '참여대상자 · 결과보기 · 참여데이타 · 불참데이타 · 미리보기',
            '참여자 SMS 발송 + 발송 내역',
        ],
    ],
    '/admin/accept' => [
        'title' => '등기접수 관리',
        'items' => [
            '접수 목록 — 설문명 · 시작일/종료일 · 참여자 · 결과/참여데이타/미리보기',
            '접수 등록 — 설문 등록 + 핸드폰 확인(필수 체크) · 설문기간 · 문항 빌더(객관식/주관식/설명글/핸드폰)',
            '용도: 분양전환 상담 예약 · 설명회 신청 · 대지권 비대면 접수 · 입주 축하 안내',
        ],
    ],
    '/admin/attorney' => [
        'title' => '위임장 관리',
        'items' => [
            '위임장 목록 — 제목 · 주최 · 참여/불참 · 참여대상자 · 데이타 · 미리보기 (설문과 동일 구조)',
            '참여자 SMS 발송',
        ],
    ],
];
$sec = $sections[$path] ?? $sections['/admin/survey'];

render_admin('placeholder', [
    'pageTitle' => $sec['title'],
    'staff'     => $staff,
    'section'   => $sec,
]);
