<?php /* 지금 파기 — 확인 단계 */
$base = $typeInfo['base'];
$sid = (int)$survey['id'];
?>
<h1 class="adm-ttl">지금 파기 — <?= h($survey['title']) ?></h1>
<p class="adm-lead">파기예정일(<?= h((string)($survey['retention_until'] ?? '미정')) ?>)을 기다리지 않고 지금 지웁니다.</p>

<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<div class="adm-stats">
  <div class="adm-stat"><b>참여 응답</b><strong><?= number_format($survey['cnt_joined']) ?></strong><span>건 삭제</span></div>
  <div class="adm-stat"><b>총인원 (대상 세대)</b><strong><?= number_format($survey['cnt_total']) ?></strong><span>세대</span></div>
</div>

<div class="alert alert--warn">
  <b>되돌릴 수 없습니다</b>
  참여 세대의 응답·답·서명·제출 핸드폰을 모두 지웁니다. 문항과 참여대상 단지, 문항별 집계 수치(참여 수·보기별 응답수)만 남고,
  이 <?= h($typeInfo['label']) ?>은(는) 더 이상 참여를 받지 않습니다.
</div>

<div class="alert alert--info">
  <b>협의회에 결과를 넘겨야 한다면 먼저 CSV 를 받으십시오</b>
  파기 후에는 세대별 답을 다시 받을 수 없습니다. CSV 의 서명 칸에는 「서명 있음」만 적힙니다.
  <p style="margin:10px 0 0"><a class="btn btn--sm" href="<?= h($base) ?>/data?id=<?= $sid ?>&amp;export=csv">결과 CSV 받기</a></p>
</div>

<form method="post" action="<?= h($base) ?>/purge?id=<?= $sid ?>" class="adm-sec"
      data-confirm="「<?= h($survey['title']) ?>」 응답 <?= number_format($survey['cnt_joined']) ?>건을 지금 파기합니다. 되돌릴 수 없습니다. 계속할까요?">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $sid ?>">
  <label style="display:flex;align-items:flex-start;gap:8px;font-weight:600;margin:0 0 14px">
    <input type="checkbox" name="confirm" value="1" required style="margin-top:6px">
    응답·답·서명이 완전히 지워지고 되돌릴 수 없다는 것을 확인했습니다.
  </label>
  <button type="submit" class="btn btn--danger">지금 파기</button>
  <a class="btn" href="<?= h($base) ?>/result?id=<?= $sid ?>">취소</a>
</form>
