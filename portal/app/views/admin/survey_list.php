<?php /* 설문/등기접수/위임장 목록 — 행 단추 6개는 기존 시스템 그대로 */
$base = $typeInfo['base'];
$isAccept = $surveyType === 'accept';
?>
<h1 class="adm-ttl"><?= h($typeInfo['label']) ?> 목록</h1>
<p class="adm-lead">
  <?= h($typeInfo['label']) ?> 건별로 대상 단지·참여 현황을 관리합니다.
  총인원은 참여대상자로 고른 단지의 전체 세대수입니다.
</p>

<?php if (!empty($_GET['saved'])): ?>
<div class="alert alert--ok"><b>저장했습니다</b><?= h($typeInfo['label']) ?> 내용과 문항이 갱신됐습니다.
  대상 단지는 행의 「참여대상자」에서 고릅니다.</div>
<?php endif; ?>

<p style="margin:0 0 14px"><a class="btn btn--sm btn--fill" href="<?= h($base) ?>/form"><?= h($typeInfo['label']) ?> 등록</a></p>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr>
      <th>번호</th><th><?= h($typeInfo['label']) ?>명</th><th>주최</th>
      <?php if ($isAccept): ?><th class="ctr">시작일</th><th class="ctr">종료일</th><?php endif; ?>
      <th class="ctr">총인원</th><th class="ctr">참여</th><th class="ctr">불참</th>
      <th class="ctr">진행</th><th class="ctr">작성일</th><th>관리</th>
    </tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
    <tr><td colspan="<?= $isAccept ? 12 : 10 ?>" class="ctr">등록된 <?= h($typeInfo['label']) ?>이(가) 없습니다.
      위의 「<?= h($typeInfo['label']) ?> 등록」으로 시작하십시오.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $open = survey_is_open($r); ?>
    <tr>
      <td class="num"><?= (int)$r['id'] ?></td>
      <td><a href="<?= h($base) ?>/form?id=<?= (int)$r['id'] ?>" style="color:var(--navy);font-weight:700"><?= h($r['title']) ?></a></td>
      <td class="nowrap"><?= h($r['organizer']) ?: '—' ?></td>
      <?php if ($isAccept): ?>
      <td class="ctr nowrap"><?= h((string)($r['starts_on'] ?? '')) ?: '—' ?></td>
      <td class="ctr nowrap"><?= h((string)($r['ends_on'] ?? '')) ?: '—' ?></td>
      <?php endif; ?>
      <td class="ctr num"><?= number_format($r['cnt_total']) ?></td>
      <td class="ctr num"><span class="mark-done"><?= number_format($r['cnt_joined']) ?></span></td>
      <td class="ctr num"><?= number_format($r['cnt_absent']) ?></td>
      <td class="ctr"><?= $open ? '<span class="mark-done">진행중</span>' : '<span class="mark-wait">마감</span>' ?></td>
      <td class="ctr nowrap"><?= h(substr((string)$r['created_at'], 0, 10)) ?></td>
      <td class="nowrap">
        <a class="btn btn--xs" href="<?= h($base) ?>/target?id=<?= (int)$r['id'] ?>">참여대상자</a>
        <a class="btn btn--xs" href="<?= h($base) ?>/result?id=<?= (int)$r['id'] ?>">결과보기</a>
        <a class="btn btn--xs" href="<?= h($base) ?>/data?id=<?= (int)$r['id'] ?>">참여데이타</a>
        <a class="btn btn--xs" href="<?= h($base) ?>/data?id=<?= (int)$r['id'] ?>&amp;mode=absent">불참데이타</a>
        <a class="btn btn--xs" href="<?= h($base) ?>/preview?id=<?= (int)$r['id'] ?>">미리보기</a>
        <a class="btn btn--xs" href="<?= h($base) ?>/form?id=<?= (int)$r['id'] ?>">조회/수정</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="<?= h($base) ?>?page=<?= $p ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">총 <?= number_format($total) ?>건 · <?= $page ?>/<?= $pages ?>쪽</span>
</div>
