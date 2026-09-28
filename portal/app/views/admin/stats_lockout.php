<h1 class="adm-ttl">조회 실패·잠금</h1>
<p class="adm-lead">손님 로그인은 세대 기준 1시간에 10회 실패하면 잠깁니다. 실패가 몰린 세대를 먼저 봅니다.</p>

<div class="adm-sec">
  <h2>최근 1시간 실패 상위 세대</h2>
  <?php if ($hot === []): ?>
  <div class="alert alert--ok" style="margin:0"><b>이상 없음</b>최근 1시간 안에 실패한 로그인이 없습니다.</div>
  <?php else: ?>
  <div class="tbl-scroll" style="border:0; margin:0">
    <table class="tbl">
      <thead><tr><th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th class="ctr">실패 수 (1시간)</th><th class="ctr">상태</th><th>마지막 시도</th></tr></thead>
      <tbody>
      <?php foreach ($hot as $r): $locked = (int)$r['fails'] >= 10; ?>
      <tr>
        <td class="nowrap"><?= h($r['complex_name'] ?? '(단지 미상)') ?></td>
        <td class="ctr"><?= h($r['dong']) ?></td>
        <td class="ctr"><?= h($r['ho']) ?></td>
        <td class="ctr num<?= $locked ? ' neg' : '' ?>"><?= (int)$r['fails'] ?></td>
        <td class="ctr"><?= $locked ? '<span class="neg">잠금 중</span>' : '<span class="mark-wait">관찰</span>' ?></td>
        <td class="nowrap"><?= h($r['last_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="adm-sec">
  <h2>최근 실패 기록 50건</h2>
  <div class="tbl-scroll" style="border:0; margin:0">
    <table class="tbl">
      <thead><tr><th>일시</th><th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th>IP</th></tr></thead>
      <tbody>
      <?php if ($recent === []): ?><tr><td colspan="5" class="ctr">실패 기록이 없습니다.</td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td class="nowrap"><?= h($r['created_at']) ?></td>
        <td class="nowrap"><?= h($r['complex_name'] ?? '(단지 미상)') ?></td>
        <td class="ctr"><?= h($r['dong']) ?></td>
        <td class="ctr"><?= h($r['ho']) ?></td>
        <td class="nowrap"><?= h($r['ip']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
