<h1 class="adm-ttl">직원 작업기록</h1>
<p class="adm-lead">admin 권한 전용 화면입니다. 관리자 화면의 모든 쓰기 작업과 민감정보 열람이 남습니다.</p>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>번호</th><th>일시</th><th>직원</th><th>작업</th><th>대상</th><th>내용</th><th>IP</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="7" class="ctr">작업기록이 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= (int)$r['id'] ?></td>
      <td class="nowrap"><?= h($r['created_at']) ?></td>
      <td class="nowrap"><?= $r['staff_name'] !== null ? h($r['staff_name']) . ' (' . h($r['login_id']) . ')' : '(시스템)' ?></td>
      <td class="nowrap"><?= h($r['action']) ?></td>
      <td class="nowrap"><?= h($r['target']) ?></td>
      <td><?= h((string)$r['detail']) ?></td>
      <td class="nowrap"><?= h($r['ip']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="/admin/stats/audit?page=<?= $p ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">총 <?= number_format($total) ?>건</span>
</div>
