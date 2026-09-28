<?php /* 미비서류 현황 — 목록 + 발급기준 안내 (SPEC 그대로) */ ?>
<div class="greet">
  <p class="greet__at"><?= h($hh['complex_name']) ?> · <?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호</p>
  <h1 class="ttl">미비서류 현황</h1>
</div>

<?php if (count($missing) === 0): ?>
<div class="alert alert--ok">
  <b>개별 미비서류가 없습니다.</b>
  <span class="s">따로 더 내실 서류가 없습니다.</span>
</div>
<?php else: ?>
<div class="card">
  <h2 class="sub">아직 받지 못한 서류</h2>
  <ul class="doclist">
    <?php foreach ($missing as $doc): ?>
    <li><?= h($doc) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="note">
  <b>서류 발급 기준</b>
  <ul>
    <li><span class="s"><b>주민등록초본</b>은 주소 변동 이력이 전부 나오게 발급해 주십시오.</span></li>
    <li><span class="s"><b>인감증명서</b>는 발급일로부터 3개월 안의 것이어야 합니다.</span></li>
    <li><span class="s">발급이 어려우시면 <a href="tel:18994252">1899-4252</a> 로 문의해 주십시오.</span></li>
  </ul>
</div>
