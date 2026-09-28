<?php /* 메인 — 기존 6개 카드 그대로 */ ?>
<div class="greet">
  <p class="greet__at"><?= h($hh['complex_name']) ?></p>
  <h1 class="ttl"><?= h($customer['owner_name']) ?> 님, 안녕하십니까</h1>
  <p class="lead">
    <span class="s"><?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호의 등기 진행 내용을 보실 수 있습니다.</span>
    <span class="s">아래에서 원하시는 항목을 눌러 주십시오.</span>
  </p>
</div>

<?php
/* 카드별 짧은 설명 — 메뉴 순서는 customer_menus() 기존 그대로 */
$descriptions = [
    '/progress' => '지금 어느 단계인지, 다음에 무엇이 남았는지 봅니다.',
    '/cost'     => '취득세부터 보수료까지 항목별 금액을 봅니다.',
    '/docs'     => '아직 내지 않으신 서류가 있는지 봅니다.',
    '/address'  => '권리증을 받으실 주소를 등록·수정합니다.',
    '/refund'   => '국민주택채권 환불 받으실 계좌를 등록합니다.',
    '/faq'      => '자주 들어오는 질문과 답을 모았습니다.',
];
?>
<div class="cards">
  <?php foreach (customer_menus() as $href => $label): ?>
  <a class="cards__item" href="<?= h($href) ?>">
    <b><?= h($label) ?></b>
    <span class="s"><?= h($descriptions[$href]) ?></span>
  </a>
  <?php endforeach; ?>
</div>
