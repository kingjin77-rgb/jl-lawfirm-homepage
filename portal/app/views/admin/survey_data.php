<?php /* 참여데이타/불참데이타 — 표 + CSV 내려받기 */
$base = $typeInfo['base'];
$isJoined = $mode === 'joined';
?>
<h1 class="adm-ttl"><?= $isJoined ? '참여데이타' : '불참데이타' ?> — <?= h($survey['title']) ?></h1>
<p class="adm-lead">
  <?= $isJoined
      ? '제출한 세대의 답 전체입니다. CSV 는 문항별 답이 열로 붙습니다.'
      : '대상 단지 세대 중 아직 제출하지 않은 세대입니다.' ?>
</p>

<p style="margin:0 0 14px">
  <a class="btn btn--sm<?= $isJoined ? ' btn--fill' : '' ?>" href="<?= h($base) ?>/data?id=<?= (int)$survey['id'] ?>">참여 (<?= number_format($isJoined ? count($rows) : 0) ?: '보기' ?>)</a>
  <a class="btn btn--sm<?= $isJoined ? '' : ' btn--fill' ?>" href="<?= h($base) ?>/data?id=<?= (int)$survey['id'] ?>&amp;mode=absent">불참<?= $isJoined ? '' : ' (' . number_format(count($rows)) . ')' ?></a>
  <a class="btn btn--sm" href="<?= h($base) ?>/data?id=<?= (int)$survey['id'] ?>&amp;mode=<?= h($mode) ?>&amp;export=csv">CSV 내려받기</a>
</p>

<div class="tbl-scroll">
  <table class="tbl">
    <?php if ($isJoined): ?>
    <thead><tr>
      <th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th>제출자</th><th>핸드폰</th><th>제출일시</th>
      <?php foreach ($questions as $q): ?><th><?= h($q['title']) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="<?= 6 + count($questions) ?>" class="ctr">아직 제출한 세대가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="nowrap"><?= h($r['complex_name']) ?></td>
      <td class="ctr"><?= h($r['dong']) ?></td>
      <td class="ctr"><?= h($r['ho']) ?></td>
      <td class="nowrap"><?= h($r['owner_name']) ?></td>
      <td class="nowrap"><?= h($r['phone']) ?: '—' ?></td>
      <td class="nowrap"><?= h($r['submitted_at']) ?></td>
      <?php foreach ($questions as $q): ?>
      <td><?= nl2br(h((string)($answersBy[(int)$r['response_id']][(int)$q['id']] ?? ''))) ?: '—' ?></td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php else: ?>
    <thead><tr><th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th>명의인</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="4" class="ctr">불참 세대가 없습니다. 대상 전 세대가 제출했습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="nowrap"><?= h($r['complex_name']) ?></td>
      <td class="ctr"><?= h($r['dong']) ?></td>
      <td class="ctr"><?= h($r['ho']) ?></td>
      <td class="nowrap"><?= h($r['owner_names']) ?: '—' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php endif; ?>
  </table>
</div>

<p style="margin-top:6px;font-size:15px;color:var(--ink-soft)">총 <?= number_format(count($rows)) ?>건</p>

<p><a class="btn" href="<?= h($base) ?>">목록으로</a>
   <a class="btn" href="<?= h($base) ?>/result?id=<?= (int)$survey['id'] ?>">결과보기</a></p>
