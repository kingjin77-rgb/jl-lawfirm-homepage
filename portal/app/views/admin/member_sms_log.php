<h1 class="adm-ttl">SMS 발송 내역</h1>
<p class="adm-lead">저장·발송된 문자 내역입니다. 업체 연동 전에 저장한 건은 「대기」 상태로 남습니다.</p>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>번호</th><th>구분</th><th>대상 아파트</th><th class="ctr">대상수</th><th>내용</th><th>작성 직원</th><th class="ctr">상태</th><th>일시</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="8" class="ctr">발송 내역이 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $m): ?>
    <tr>
      <td class="num"><?= (int)$m['id'] ?></td>
      <td class="ctr nowrap"><?= h($m['msg_type']) ?></td>
      <td class="nowrap"><?= h($m['complex_name'] ?? '—') ?></td>
      <td class="ctr num"><?= number_format((int)$m['target_cnt']) ?></td>
      <td style="max-width:420px"><?= nl2br(h(mb_substr($m['body'], 0, 120) . (mb_strlen($m['body']) > 120 ? '…' : ''))) ?></td>
      <td class="nowrap"><?= h($m['staff_name'] ?? '—') ?></td>
      <td class="ctr nowrap"><?= $m['status'] === '대기' ? '<span class="mark-wait" style="color:var(--gold); font-weight:700">대기</span>' : h($m['status']) ?></td>
      <td class="nowrap"><?= h($m['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="/admin/member/sms-log?page=<?= $p ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">총 <?= number_format($total) ?>건</span>
</div>
