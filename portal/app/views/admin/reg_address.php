<h1 class="adm-ttl">권리증수령주소 현황</h1>
<p class="adm-lead">손님이 등록한 권리증 수령 주소(세대별 최신)입니다. 열람 기록이 작업기록에 남습니다.</p>

<?php $filterAction = '/admin/registration/address'; $filterSteps = false; require __DIR__ . '/_reg_filter.php'; ?>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th>이름</th><th class="ctr">우편번호</th><th>주소</th><th>등록일</th><th class="ctr">상세</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="8" class="ctr">등록된 주소가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="nowrap"><?= h($r['complex_name']) ?></td>
      <td class="ctr"><?= h($r['dong']) ?></td>
      <td class="ctr"><?= h($r['ho']) ?></td>
      <td class="nowrap"><?= h($r['owner_name'] ?? '—') ?></td>
      <td class="ctr nowrap"><?= h($r['zip']) ?: '—' ?></td>
      <td><?= h($r['addr1']) ?> <?= h($r['addr2']) ?></td>
      <td class="nowrap"><?= h($r['created_at']) ?></td>
      <td class="ctr"><a class="btn btn--xs" href="/admin/registration/form?hid=<?= (int)$r['hid'] ?>">상세</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="/admin/registration/address?<?= h(reg_filters_query($f, ['page' => $p])) ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">총 <?= number_format($total) ?>건</span>
</div>
