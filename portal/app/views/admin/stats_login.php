<h1 class="adm-ttl">일자별 손님 로그인</h1>
<p class="adm-lead">최근 30일 동안의 손님 로그인 성공·실패 횟수입니다.</p>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>날짜</th><th class="ctr">성공</th><th class="ctr">실패</th><th style="width:45%">성공 추이</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="4" class="ctr">최근 30일 로그인 기록이 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $w = (int)round((int)$r['ok_cnt'] / $max * 100); ?>
    <tr>
      <td class="nowrap"><?= h($r['d']) ?></td>
      <td class="ctr num"><?= number_format((int)$r['ok_cnt']) ?></td>
      <td class="ctr num<?= (int)$r['fail_cnt'] > 0 ? ' neg' : '' ?>"><?= number_format((int)$r['fail_cnt']) ?></td>
      <td><span style="display:inline-block; height:14px; width:<?= max(2, $w) ?>%; background:var(--navy); border-radius:4px; vertical-align:middle"></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
