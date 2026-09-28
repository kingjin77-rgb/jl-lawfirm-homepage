<?php /* 자주묻는질문 — 분류 탭 + details 아코디언(스크립트 불필요) */ ?>
<div class="greet">
  <h1 class="ttl">자주묻는질문</h1>
  <p class="lead">
    <span class="s">등기 진행 중에 자주 들어오는 질문과 답을 모았습니다.</span>
  </p>
</div>

<nav class="cats" aria-label="질문 분류">
  <?php foreach ($categories as $c): ?>
  <a href="/faq?cat=<?= urlencode($c) ?>"<?= $cat === $c ? ' class="is-on"' : '' ?>><?= h($c) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (count($faqs) === 0): ?>
<div class="alert alert--info">
  <b>등록된 질문이 아직 없습니다.</b>
  <span class="s">궁금하신 내용은 <a href="tel:18994252">1899-4252</a> 로 문의해 주십시오.</span>
</div>
<?php else: ?>
<div class="faq">
  <?php foreach ($faqs as $f): ?>
  <details class="faq__item">
    <summary><span class="faq__cat"><?= h($f['category']) ?></span><?= h($f['question']) ?></summary>
    <div class="faq__a"><?= nl2br(h($f['answer'])) ?></div>
  </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>
