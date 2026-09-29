<?php /* 결과보기 — 문항별 집계 */
$base = $typeInfo['base'];
?>
<h1 class="adm-ttl">결과보기 — <?= h($survey['title']) ?></h1>
<p class="adm-lead">문항별 집계입니다. 개별 답 전체는 「참여데이타」에서 CSV 로 내려받습니다.</p>

<div class="adm-stats">
  <div class="adm-stat"><b>총인원 (대상 세대)</b><strong><?= number_format($survey['cnt_total']) ?></strong><span>세대</span></div>
  <div class="adm-stat"><b>참여</b><strong><?= number_format($survey['cnt_joined']) ?></strong><span>세대</span></div>
  <div class="adm-stat"><b>불참</b><strong><?= number_format($survey['cnt_absent']) ?></strong><span>세대</span></div>
  <div class="adm-stat"><b>참여율</b>
    <strong><?= $survey['cnt_total'] > 0 ? number_format($survey['cnt_joined'] / $survey['cnt_total'] * 100, 1) : '0.0' ?>%</strong>
    <span>총인원 대비</span></div>
</div>

<?php if ($questions === []): ?>
<div class="alert alert--warn"><b>문항이 없습니다</b>「조회/수정」에서 문항을 만들어 주십시오.</div>
<?php endif; ?>

<?php $no = 0; foreach ($questions as $q): ?>
  <?php if ($q['qtype'] === 'note'): ?>
  <div class="adm-sec">
    <h2>설명글</h2>
    <div class="alert alert--info" style="margin:0"><b>안내 문구 (집계 없음)</b><?= nl2br(h($q['title'])) ?></div>
  </div>
  <?php elseif ($q['qtype'] === 'choice'): $no++; ?>
  <div class="adm-sec">
    <h2>문항 <?= $no ?>. <?= h($q['title']) ?> <span style="font-weight:500">(객관식 · 응답 <?= number_format($q['answered']) ?>건)</span></h2>
    <div class="tbl-scroll" style="border:0;margin:0">
      <table class="tbl">
        <thead><tr><th>보기</th><th class="ctr" style="width:110px">응답수</th><th style="width:44%">비율</th></tr></thead>
        <tbody>
        <?php foreach ($q['tally'] as $opt => $n):
            $pct = $q['answered'] > 0 ? $n / $q['answered'] * 100 : 0; ?>
        <tr>
          <td><?= h((string)$opt) ?></td>
          <td class="ctr num"><?= number_format($n) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="flex:1;height:14px;background:var(--bg);border-radius:7px;overflow:hidden">
                <div style="height:100%;width:<?= number_format($pct, 1) ?>%;background:var(--navy)"></div>
              </div>
              <b style="min-width:56px;text-align:right;font-variant-numeric:tabular-nums"><?= number_format($pct, 1) ?>%</b>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: $no++; ?>
  <div class="adm-sec">
    <h2>문항 <?= $no ?>. <?= h($q['title']) ?>
      <span style="font-weight:500">(<?= $q['qtype'] === 'phone' ? '핸드폰' : '주관식' ?> · 응답 <?= number_format($q['answered']) ?>건)</span></h2>
    <?php if ($q['texts'] === []): ?>
    <div class="alert alert--info" style="margin:0"><b>응답 없음</b>아직 이 문항에 답한 세대가 없습니다.</div>
    <?php else: ?>
    <div class="tbl-scroll" style="border:0;margin:0">
      <table class="tbl">
        <thead><tr><th class="ctr" style="width:70px">동</th><th class="ctr" style="width:70px">호수</th><th style="width:120px">제출자</th><th>답</th></tr></thead>
        <tbody>
        <?php foreach ($q['texts'] as $t): ?>
        <tr>
          <td class="ctr"><?= h($t['dong']) ?></td>
          <td class="ctr"><?= h($t['ho']) ?></td>
          <td class="nowrap"><?= h($t['owner_name']) ?></td>
          <td><?= nl2br(h($t['value'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
<?php endforeach; ?>

<p><a class="btn" href="<?= h($base) ?>">목록으로</a>
   <a class="btn" href="<?= h($base) ?>/data?id=<?= (int)$survey['id'] ?>">참여데이타</a>
   <a class="btn" href="<?= h($base) ?>/data?id=<?= (int)$survey['id'] ?>&amp;mode=absent">불참데이타</a></p>
