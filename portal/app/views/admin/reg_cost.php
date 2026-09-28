<h1 class="adm-ttl">등기비용 현황</h1>
<p class="adm-lead">세대별 11항목 비용과 입금·차액을 한 표로 봅니다. 표는 옆으로 넘겨 볼 수 있습니다.</p>

<?php $filterAction = '/admin/registration/cost'; require __DIR__ . '/_reg_filter.php'; ?>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr>
      <th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th>이름</th>
      <?php foreach (cost_item_labels() as $label): ?><th class="num"><?= h($label) ?></th><?php endforeach; ?>
      <th class="num">합계</th><th class="ctr">입금일</th><th class="num">입금액</th><th class="num">차액</th><th class="ctr">상세</th>
    </tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="20" class="ctr">조건에 맞는 세대가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $diff = (int)($r['diff'] ?? 0); ?>
    <tr>
      <td class="nowrap"><?= h($r['complex_name']) ?></td>
      <td class="ctr"><?= h($r['dong']) ?></td>
      <td class="ctr"><?= h($r['ho']) ?></td>
      <td class="nowrap"><?= h($r['owners'][0]['name'] ?? '—') ?><?= count($r['owners']) > 1 ? ' 외' : '' ?></td>
      <?php foreach (array_keys(cost_item_labels()) as $col): ?>
      <td class="num"><?= number_format((int)($r[$col] ?? 0)) ?></td>
      <?php endforeach; ?>
      <td class="num"><b><?= number_format((int)($r['total'] ?? 0)) ?></b></td>
      <td class="ctr nowrap"><?= h((string)($r['paid_date'] ?? '')) ?: '—' ?></td>
      <td class="num"><?= number_format((int)($r['paid_amount'] ?? 0)) ?></td>
      <td class="num<?= $diff < 0 ? ' neg' : '' ?>"><?= number_format($diff) ?></td>
      <td class="ctr"><a class="btn btn--xs" href="/admin/registration/form?hid=<?= (int)$r['id'] ?>">상세</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="/admin/registration/cost?<?= h(reg_filters_query($f, ['page' => $p])) ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">
    총 <?= number_format($total) ?>건 ·
    <a href="/admin/registration/export?<?= h(reg_filters_query($f)) ?>">현재 조건 CSV 내려받기</a>
  </span>
</div>
