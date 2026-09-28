<?php
/**
 * 공용 헬퍼 — 출력 이스케이프, 리다이렉트, 뷰 렌더, 금액 표기.
 */

/** HTML 이스케이프 — 화면에 찍는 모든 값은 이걸 거친다. */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * 뷰 렌더 — app/views/<name>.php 를 layout.php 로 감싼다.
 * $data 의 키가 뷰 안에서 변수로 풀린다.
 */
function render(string $view, array $data = []): void
{
    $viewFile = __DIR__ . '/views/' . basename($view) . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit('view not found: ' . h($view));
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}

/** 원 단위 금액 → "1,234,560원" */
function won(int|string $amount): string
{
    return number_format((int)$amount) . '원';
}

/** 8단계 이름 — SPEC 의 기존 명칭 그대로. 순서가 곧 단계 번호다. */
function progress_step_names(): array
{
    return [
        1 => '등기서류수령',
        2 => '취득세신고',
        3 => '등기비용통보',
        4 => '등기비입금확인',
        5 => '건설사등기서류수령',
        6 => '등기소서류접수',
        7 => '등기완료',
        8 => '권리증교부',
    ];
}

/** 비용 11항목 — 컬럼명 ↔ 표시명. 표시 순서도 기존 그대로. */
function cost_item_labels(): array
{
    return [
        'acq_tax'       => '취득세',
        'bond_transfer' => '이전채권',
        'bond_mortgage' => '설정채권',
        'stamp_tax'     => '인지대',
        'cert_stamp'    => '증지대',
        'via_stamp'     => '경유증표',
        'trust_cancel'  => '신탁말소',
        'cert_fees'     => '제증명',
        'fee'           => '보수료',
        'vat'           => '부가세',
        'etc_amt'       => '기타',
    ];
}

/** 손님 화면 6개 메뉴 — 홈 카드와 탭 내비가 함께 쓴다. */
function customer_menus(): array
{
    return [
        '/progress' => '등기진행 현황',
        '/cost'     => '등기비용 내역서',
        '/docs'     => '미비서류 현황',
        '/address'  => '권리증 수령 주소',
        '/refund'   => '채권 환불 신청',
        '/faq'      => '자주묻는질문',
    ];
}

/** 로그인 세대의 단지/동/호/명의인들 — 헤더 인사와 각 페이지가 쓴다. */
function household_context(int $householdId): ?array
{
    $row = db_row(
        'SELECT h.id, h.dong, h.ho, c.id AS complex_id, c.name AS complex_name,
                c.bank_name, c.bank_account, c.bank_holder
           FROM household h JOIN complex c ON c.id = h.complex_id
          WHERE h.id = ?',
        [$householdId]
    );
    if ($row === null) {
        return null;
    }
    $row['owners'] = db_all(
        'SELECT name, is_primary FROM owner WHERE household_id = ? ORDER BY is_primary DESC, id',
        [$householdId]
    );
    return $row;
}
