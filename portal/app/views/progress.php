<?php /* 등기진행 현황 — STEP 01~08 카드 + 하단 안내 3줄 (SPEC 그대로) */ ?>
<div class="greet">
  <p class="greet__at"><?= h($hh['complex_name']) ?> · <?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호</p>
  <h1 class="ttl">등기진행 현황</h1>
  <p class="lead">
    <span class="s"><?= h($customer['owner_name']) ?> 님 세대의 등기가 어디까지 왔는지 보여드립니다.</span>
  </p>
</div>

<?php if (!$hasRecord): ?>
<div class="alert alert--info">
  <b>준비중입니다.</b>
  <span class="s">등기 서류가 접수되면 진행 단계가 표시됩니다.</span>
</div>
<?php endif; ?>

<ol class="steps">
  <?php foreach ($steps as $n => $s): ?>
  <li class="steps__item is-<?= $s['state'] === '완료' ? 'done' : ($s['state'] === '진행중' ? 'now' : 'wait') ?>">
    <span class="steps__no">STEP <?= str_pad((string)$n, 2, '0', STR_PAD_LEFT) ?></span>
    <b class="steps__name"><?= h($s['label']) ?></b>
    <span class="steps__state"><?= h($s['state']) ?></span>
    <?php if ($s['date'] !== null): ?>
    <span class="steps__date"><?= h($s['date']) ?></span>
    <?php endif; ?>
  </li>
  <?php endforeach; ?>
</ol>

<?php if (!empty($sentDate)): ?>
<div class="alert alert--ok" role="status">
  <b>권리증을 <?= h((string)$sentDate) ?> 에 발송해 드렸습니다.</b>
  <span class="s">등록하신 수령 주소로 보내드렸습니다. 일주일 넘게 도착하지 않으면 1899-4252 로 연락 주십시오.</span>
</div>
<?php endif; ?>

<div class="note">
  <b>알아두실 내용</b>
  <ul>
    <li><span class="s">취득세는 잔금일(취득일)로부터 60일 안에 신고·납부해야 합니다.</span></li>
    <li><span class="s">소유권이전등기도 잔금일로부터 60일 안에 신청해야 합니다.</span></li>
    <li><span class="s">서류 접수부터 권리증 교부까지는 통상 50일 이상 걸립니다.</span></li>
  </ul>
</div>
