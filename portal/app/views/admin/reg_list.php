<h1 class="adm-ttl">등기진행 현황</h1>
<p class="adm-lead">세대별 8단계 진행 상황입니다. 상세보기에서 단계·비용·환불·미비서류를 고칩니다.</p>

<?php $filterAction = '/admin/registration'; require __DIR__ . '/_reg_filter.php'; ?>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr>
      <th>번호</th><th>아파트명</th><th class="ctr">동</th><th class="ctr">호수</th><th>이름 (생년월일)</th>
      <?php foreach (progress_step_names() as $n => $label): ?>
      <th class="ctr" title="<?= h($label) ?>"><?= $n ?>.<?= h(mb_substr($label, 0, 4)) ?><?= mb_strlen($label) > 4 ? '…' : '' ?></th>
      <?php endforeach; ?>
      <th class="ctr">상세</th>
    </tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="14" class="ctr">조건에 맞는 세대가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= (int)$r['id'] ?></td>
      <td class="nowrap"><?= h($r['complex_name']) ?></td>
      <td class="ctr"><?= h($r['dong']) ?></td>
      <td class="ctr"><?= h($r['ho']) ?></td>
      <td>
        <?php if ($r['owners'] === []): ?>—<?php endif; ?>
        <?php foreach ($r['owners'] as $o): ?>
        <span class="nowrap" style="display:block"><?= h($o['name']) ?> (<?= h($o['birth6']) ?>)</span>
        <?php endforeach; ?>
      </td>
      <?php for ($n = 1; $n <= 8; $n++): $done = progress_step_complete($r, $n); ?>
      <td class="ctr"><?= $done ? '<span class="mark-done">완료</span>' : '<span class="mark-wait">—</span>' ?></td>
      <?php endfor; ?>
      <td class="ctr"><a class="btn btn--xs" href="/admin/registration/form?hid=<?= (int)$r['id'] ?>">상세보기</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="/admin/registration?<?= h(reg_filters_query($f, ['page' => $p])) ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">
    총 <?= number_format($total) ?>건 · <?= $page ?>/<?= $pages ?>쪽 ·
    <a href="/admin/registration/export?<?= h(reg_filters_query($f)) ?>">현재 조건 CSV 내려받기</a>
  </span>
</div>
