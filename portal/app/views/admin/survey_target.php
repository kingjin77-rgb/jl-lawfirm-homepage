<?php /* 참여대상자 — 아파트(단지) 단위 선택 */
$base = $typeInfo['base'];
?>
<h1 class="adm-ttl">참여대상자 — <?= h($survey['title']) ?></h1>
<p class="adm-lead">
  아파트 단위로 대상을 고릅니다. 고른 단지의 전 세대가 총인원(모수)이 되고,
  그 세대의 손님 홈에 참여 카드가 나옵니다.
</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><b>저장했습니다</b><?= h($notice) ?></div><?php endif; ?>

<form method="post" action="<?= h($base) ?>/target?id=<?= (int)$survey['id'] ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$survey['id'] ?>">
  <div class="adm-sec">
    <h2>대상 아파트</h2>
    <?php if ($complexes === []): ?>
    <div class="alert alert--warn" style="margin:0">
      <b>등록된 아파트가 없습니다</b>
      「인적사항 관리 → 아파트 관리」에서 단지를 먼저 등록해 주십시오.
    </div>
    <?php else: ?>
    <div class="adm-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
      <?php foreach ($complexes as $c): ?>
      <label style="display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:8px;padding:12px 14px;background:var(--white);font-size:15px">
        <input type="checkbox" name="complex_ids[]" value="<?= (int)$c['id'] ?>"<?= in_array((int)$c['id'], $targets, true) ? ' checked' : '' ?>>
        <b style="color:var(--navy)"><?= h($c['name']) ?></b>
        <span style="margin-left:auto;color:var(--ink-soft)"><?= number_format((int)$c['hh_cnt']) ?>세대</span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <button type="submit" class="btn btn--fill">대상 저장</button>
  <a class="btn" href="<?= h($base) ?>">목록으로</a>
</form>
