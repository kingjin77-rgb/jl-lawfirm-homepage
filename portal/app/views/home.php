<?php /* 메인 — 기존 6개 카드 + 참여 카드 + 운영정보 + 팝업 */ ?>
<div class="greet">
  <p class="greet__at"><?= h($hh['complex_name']) ?></p>
  <h1 class="ttl"><?= h($customer['owner_name']) ?> 님, 안녕하십니까</h1>
  <p class="lead">
    <span class="s"><?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호의 등기 진행 내용을 보실 수 있습니다.</span>
    <span class="s">아래에서 원하시는 항목을 눌러 주십시오.</span>
  </p>
</div>

<?php if ($participations !== []): ?>
<?php $typeLabels = ['survey' => '설문', 'accept' => '등기접수', 'attorney' => '위임장']; ?>
<div class="card" style="margin-bottom:20px" id="participate-box">
  <h2 class="sub">지금 참여하실 수 있습니다</h2>
  <?php foreach ($participations as $p): ?>
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin-bottom:10px">
    <div style="flex:1 1 260px;min-width:0">
      <p style="margin:0 0 2px;font-size:14.5px;font-weight:700;color:var(--gold)"><?= h($typeLabels[$p['type']] ?? '설문') ?></p>
      <b style="display:block;font-size:16.5px;color:var(--navy)"><?= h($p['title']) ?></b>
      <?php if (!empty($p['starts_on']) || !empty($p['ends_on'])): ?>
      <span class="s" style="font-size:15px;color:var(--ink-soft)">
        기간: <?= h((string)($p['starts_on'] ?? '')) ?: '제한 없음' ?> ~ <?= h((string)($p['ends_on'] ?? '')) ?: '제한 없음' ?>
      </span>
      <?php endif; ?>
      <?php if ($p['responded']): ?>
      <span class="s" style="font-size:15px;color:var(--ok);font-weight:700">제출 완료 — 다시 제출하면 수정됩니다</span>
      <?php endif; ?>
    </div>
    <a class="btn<?= $p['responded'] ? '' : ' btn--fill' ?>" href="/participate?id=<?= (int)$p['id'] ?>">
      <?= $p['responded'] ? '내용 수정' : '참여하기' ?></a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

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

<?php if ($ops !== [] || $opsNotice !== ''): ?>
<div class="card" style="margin-top:20px">
  <h2 class="sub">이용 안내</h2>
  <?php if ($ops !== []): ?>
  <dl class="kv">
    <?php foreach ($ops as $label => $value): ?>
    <div><dt><?= h($label) ?></dt><dd><?= h($value) ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <?php endif; ?>
  <?php if ($opsNotice !== ''): ?>
  <div class="alert alert--info" style="margin:<?= $ops !== [] ? '14px' : '0' ?> 0 0">
    <b>안내</b>
    <?= nl2br(h($opsNotice)) ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($popup !== null): ?>
<div id="jl-popup" role="dialog" aria-modal="true" aria-labelledby="jl-popup-ttl"
     style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(9,24,69,.55)">
  <div class="card" style="max-width:520px;width:100%;margin:0;box-shadow:0 12px 40px rgba(0,0,0,.25)">
    <?php if ($popup['title'] !== ''): ?>
    <h2 class="sub" id="jl-popup-ttl" style="font-size:20px"><?= h($popup['title']) ?></h2>
    <?php endif; ?>
    <?php if ($popup['body'] !== ''): ?>
    <p style="margin:0 0 18px;font-size:16px;line-height:1.7"><?= nl2br(h($popup['body'])) ?></p>
    <?php endif; ?>
    <button type="button" class="btn btn--fill btn--full" id="jl-popup-close">확인했습니다</button>
  </div>
</div>
<script>
document.getElementById('jl-popup-close').addEventListener('click', function () {
  document.getElementById('jl-popup').remove();
});
</script>
<?php endif; ?>
