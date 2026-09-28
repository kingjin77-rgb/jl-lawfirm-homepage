<h1 class="adm-ttl">채권환불 신청현황</h1>
<p class="adm-lead">손님이 등록한 환불 계좌(세대별 최신)입니다. 환불일은 각 행에서 바로 넣습니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<?php $filterAction = '/admin/registration/refund'; $filterSteps = false; require __DIR__ . '/_reg_filter.php'; ?>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th>이름</th><th>은행</th><th>계좌번호</th><th>예금주</th><th>신청일</th><th>환불일</th><th class="ctr">상세</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="10" class="ctr">등록된 환불 신청이 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="nowrap"><?= h($r['complex_name']) ?></td>
      <td class="ctr"><?= h($r['dong']) ?></td>
      <td class="ctr"><?= h($r['ho']) ?></td>
      <td class="nowrap"><?= h($r['owner_name'] ?? '—') ?></td>
      <td class="nowrap"><?= h($r['bank']) ?></td>
      <td class="nowrap"><?= h($r['account']) ?></td>
      <td class="nowrap"><?= h($r['holder']) ?></td>
      <td class="nowrap"><?= h(substr((string)$r['created_at'], 0, 10)) ?></td>
      <td class="nowrap">
        <form method="post" action="/admin/registration/refund?<?= h(reg_filters_query($f, ['page' => $page])) ?>"
              style="display:flex; gap:6px; align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="refund_id" value="<?= (int)$r['id'] ?>">
          <input type="date" name="refund_date" value="<?= h((string)($r['refund_date'] ?? '')) ?>"
                 style="font:inherit; font-size:14px; border:1px solid var(--line); border-radius:6px; padding:4px 6px">
          <button type="submit" class="btn btn--xs">저장</button>
        </form>
      </td>
      <td class="ctr"><a class="btn btn--xs" href="/admin/registration/form?hid=<?= (int)$r['hid'] ?>">상세</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="/admin/registration/refund?<?= h(reg_filters_query($f, ['page' => $p])) ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">총 <?= number_format($total) ?>건</span>
</div>
