<h1 class="adm-ttl"><?= h($staff['name']) ?> 님, 안녕하십니까</h1>
<p class="adm-lead">등기 포털의 오늘 현황입니다. 상단 메뉴에서 각 업무 화면으로 이동합니다.</p>

<div class="adm-stats">
  <a class="adm-stat" href="/admin/member/complex"><b>아파트(단지)</b><strong><?= number_format($counts['complex']) ?></strong><span> 곳</span></a>
  <a class="adm-stat" href="/admin/member/household"><b>세대</b><strong><?= number_format($counts['household']) ?></strong><span> 세대</span></a>
  <a class="adm-stat" href="/admin/registration/address"><b>권리증수령주소</b><strong><?= number_format($counts['address']) ?></strong><span> 건</span></a>
  <a class="adm-stat" href="/admin/registration/refund"><b>채권환불 신청</b><strong><?= number_format($counts['refund']) ?></strong><span> 건</span></a>
</div>

<div class="adm-sec">
  <h2>진행단계별 완료 세대</h2>
  <div class="tbl-scroll" style="border:0; margin:0">
    <table class="tbl">
      <thead><tr>
        <?php foreach ($stepCounts as $n => $s): ?><th class="ctr"><?= $n ?>. <?= h($s['label']) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody><tr>
        <?php foreach ($stepCounts as $s): ?><td class="ctr num"><?= number_format($s['count']) ?></td><?php endforeach; ?>
      </tr></tbody>
    </table>
  </div>
</div>

<div class="adm-sec">
  <h2>최근 손님 입력 5건</h2>
  <?php if ($recent === []): ?>
  <div class="alert alert--info" style="margin:0"><b>아직 입력이 없습니다</b>손님이 주소·환불 계좌를 등록하면 여기 나옵니다.</div>
  <?php else: ?>
  <div class="tbl-scroll" style="border:0; margin:0">
    <table class="tbl">
      <thead><tr><th>구분</th><th>세대</th><th>내용 (마스킹)</th><th>등록일시</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td class="nowrap"><?= h($r['type']) ?></td>
        <td class="nowrap"><?= h($r['where']) ?></td>
        <td><?= h($r['preview']) ?></td>
        <td class="nowrap"><?= h($r['at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="margin:10px 0 0; font-size:15px">전체 내용은
    <a href="/admin/registration/address">권리증수령주소 현황</a> ·
    <a href="/admin/registration/refund">채권환불 신청현황</a>에서 봅니다.</p>
  <?php endif; ?>
</div>

<div class="adm-sec">
  <h2>바로 가기</h2>
  <p style="margin:0; display:flex; gap:10px; flex-wrap:wrap">
    <a class="btn btn--sm" href="/admin/registration/upload">등기진행 등록 (엑셀)</a>
    <a class="btn btn--sm" href="/admin/registration">등기진행 현황</a>
    <a class="btn btn--sm" href="/admin/member/household">세대/소유자 관리</a>
    <a class="btn btn--sm" href="/admin/member/sms">SMS 발송</a>
  </p>
</div>
