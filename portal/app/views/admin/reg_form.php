<h1 class="adm-ttl"><?= h($hh['complex_name']) ?> <?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호</h1>
<p class="adm-lead">등기진행 상세입니다. 저장한 내용은 손님 화면에 바로 반영됩니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<!-- ① 인적사항 -->
<div class="adm-sec">
  <h2>① 인적사항</h2>
  <div class="tbl-scroll" style="border:0; margin:0">
    <table class="tbl">
      <thead><tr><th>아파트</th><th class="ctr">동</th><th class="ctr">호수</th><th>명의인 (생년월일)</th><th>핸드폰</th><th>등록일</th><th>수정일</th></tr></thead>
      <tbody><tr>
        <td class="nowrap"><?= h($hh['complex_name']) ?></td>
        <td class="ctr"><?= h($hh['dong']) ?></td>
        <td class="ctr"><?= h($hh['ho']) ?></td>
        <td><?php foreach ($owners as $o): ?>
          <span class="nowrap" style="display:block"><?= h($o['name']) ?> (<?= h($o['birth6']) ?>)<?= $o['is_primary'] ? '' : ' · 공동' ?></span>
        <?php endforeach; ?></td>
        <td><?php foreach ($owners as $o): ?><span class="nowrap" style="display:block"><?= h($o['phone']) ?: '—' ?></span><?php endforeach; ?></td>
        <td class="nowrap"><?= h(substr((string)$hh['created_at'], 0, 10)) ?></td>
        <td class="nowrap"><?= h(substr((string)$hh['updated_at'], 0, 10)) ?></td>
      </tr></tbody>
    </table>
  </div>
  <p style="margin:12px 0 0"><a class="btn btn--xs" href="/admin/member/household?edit=<?= (int)$hh['id'] ?>#hh-form">명의인 수정 (세대/소유자 관리)</a></p>
</div>

<!-- ② 8단계 + ⑥ 미비서류 + ⑦ 관리자메모 (한 폼) -->
<form method="post" action="/admin/registration/form?hid=<?= (int)$hh['id'] ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="progress">
  <input type="hidden" name="hid" value="<?= (int)$hh['id'] ?>">
  <div class="adm-sec">
    <h2>② 등기진행 8단계</h2>
    <div class="adm-steps">
      <?php foreach (progress_step_names() as $n => $label):
          $date = $prog["step{$n}_date"] ?? null;
          $done = progress_step_complete($prog, $n); ?>
      <div class="adm-step<?= $done ? ' is-done' : '' ?>">
        <span class="adm-step__no">STEP <?= str_pad((string)$n, 2, '0', STR_PAD_LEFT) ?></span>
        <span class="adm-step__name"><?= h($label) ?></span>
        <input type="date" name="step<?= $n ?>_date" value="<?= h((string)$date) ?>" aria-label="<?= h($label) ?> 완료일">
        <label class="adm-step__done">
          <input type="checkbox" name="step<?= $n ?>_done" value="1"<?= $done ? ' checked' : '' ?>> 완료
        </label>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="adm-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top:14px">
      <div class="adm-field">
        <label>권리증 발송일 (엑셀 38열)</label>
        <input type="date" name="sent_date" value="<?= h((string)($prog['sent_date'] ?? '')) ?>">
      </div>
    </div>
    <div class="alert alert--info" style="margin:14px 0 0">
      <b>완료 처리 기준</b>
      날짜를 넣으면 자동으로 완료가 되고, 날짜 없이 「완료」만 체크해도 됩니다 (기존 엑셀과 같은 방식).
      권리증 발송일은 손님의 진행현황 화면(권리증교부 단계)에 함께 나옵니다.
    </div>
  </div>

  <div class="adm-sec">
    <h2>⑥ 미비서류</h2>
    <div class="adm-field">
      <textarea name="missing_docs" placeholder="예: 초본 1통 (줄 단위로 적으면 손님 화면에 항목별로 나옵니다)"><?= h((string)($prog['missing_docs'] ?? '')) ?></textarea>
    </div>
  </div>

  <div class="adm-sec">
    <h2>⑦ 관리자메모 (손님에게 보이지 않음)</h2>
    <div class="adm-field">
      <textarea name="memo_admin"><?= h((string)($prog['memo_admin'] ?? '')) ?></textarea>
    </div>
  </div>
  <p style="margin:0 0 24px"><button type="submit" class="btn btn--fill">②·⑥·⑦ 저장</button></p>
</form>

<!-- ③ 비용 + ④ 입금 (한 폼) -->
<form method="post" action="/admin/registration/form?hid=<?= (int)$hh['id'] ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="cost">
  <input type="hidden" name="hid" value="<?= (int)$hh['id'] ?>">
  <div class="adm-sec">
    <h2>③ 등기비용 내역서 (11항목)</h2>
    <div class="adm-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))">
      <?php foreach (cost_item_labels() as $col => $label): ?>
      <div class="adm-field">
        <label><?= h($label) ?></label>
        <input type="text" name="<?= h($col) ?>" class="cost-in" inputmode="numeric"
               value="<?= number_format((int)($cost[$col] ?? 0)) ?>" style="text-align:right">
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="adm-sec">
    <h2>④ 입금 확인</h2>
    <div class="adm-grid" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr))">
      <div class="adm-field"><label>합계 (자동)</label>
        <input type="text" id="cost-total" readonly style="text-align:right; background:var(--bg)"
               value="<?= number_format((int)($cost['total'] ?? 0)) ?>"></div>
      <div class="adm-field"><label>입금일</label>
        <input type="date" name="paid_date" value="<?= h((string)($cost['paid_date'] ?? '')) ?>"></div>
      <div class="adm-field"><label>입금액</label>
        <input type="text" name="paid_amount" id="cost-paid" inputmode="numeric" style="text-align:right"
               value="<?= number_format((int)($cost['paid_amount'] ?? 0)) ?>"></div>
      <div class="adm-field"><label>차액 (입금액 − 합계, 자동)</label>
        <input type="text" id="cost-diff" readonly style="text-align:right; background:var(--bg)"
               value="<?= number_format((int)($cost['diff'] ?? 0)) ?>"></div>
    </div>
  </div>
  <p style="margin:0 0 24px"><button type="submit" class="btn btn--fill">③·④ 저장</button></p>
</form>

<!-- ⑤ 채권환불 신청 내용 -->
<div class="adm-sec">
  <h2>⑤ 채권환불 신청 내용</h2>
  <?php if ($refund === null): ?>
  <div class="alert alert--info" style="margin:0"><b>신청 없음</b>손님이 아직 환불 계좌를 등록하지 않았습니다.</div>
  <?php else: ?>
  <div class="tbl-scroll" style="border:0; margin:0 0 14px">
    <table class="tbl">
      <thead><tr><th>신청일자</th><th>은행</th><th>계좌번호</th><th>예금주</th><th>환불일</th></tr></thead>
      <tbody><tr>
        <td class="nowrap"><?= h($refund['created_at']) ?></td>
        <td class="nowrap"><?= h($refund['bank']) ?></td>
        <td class="nowrap"><?= h($refund['account']) ?></td>
        <td class="nowrap"><?= h($refund['holder']) ?></td>
        <td class="nowrap"><?= h((string)($refund['refund_date'] ?? '')) ?: '<span class="mark-wait">미입력</span>' ?></td>
      </tr></tbody>
    </table>
  </div>
  <form method="post" action="/admin/registration/form?hid=<?= (int)$hh['id'] ?>" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="refund_date">
    <input type="hidden" name="hid" value="<?= (int)$hh['id'] ?>">
    <input type="hidden" name="refund_id" value="<?= (int)$refund['id'] ?>">
    <div class="adm-field"><label>환불일 입력 (직원)</label>
      <input type="date" name="refund_date" value="<?= h((string)($refund['refund_date'] ?? '')) ?>"></div>
    <button type="submit" class="btn btn--sm">환불일 저장</button>
  </form>
  <?php endif; ?>
</div>

<!-- 참고: 권리증 수령 주소 (손님 등록분) -->
<div class="adm-sec">
  <h2>참고 — 권리증 수령 주소 (손님 등록분)</h2>
  <?php if ($address === null): ?>
  <div class="alert alert--info" style="margin:0"><b>등록 없음</b>손님이 아직 수령 주소를 등록하지 않았습니다.</div>
  <?php else: ?>
  <p style="margin:0; font-size:15px">
    (<?= h($address['zip']) ?>) <?= h($address['addr1']) ?> <?= h($address['addr2']) ?>
    <span style="color:var(--ink-soft)"> — 등록 <?= h($address['created_at']) ?></span>
  </p>
  <?php endif; ?>
</div>

<p><a class="btn" href="/admin/registration">목록으로</a></p>

<script>
(function () {
  var items = document.querySelectorAll('.cost-in');
  var paid = document.getElementById('cost-paid');
  var totalOut = document.getElementById('cost-total');
  var diffOut = document.getElementById('cost-diff');
  function num(v) { var n = parseInt(String(v).replace(/[^\-0-9]/g, ''), 10); return isNaN(n) ? 0 : n; }
  function fmt(n) { return n.toLocaleString('ko-KR'); }
  function recalc() {
    var t = 0;
    items.forEach(function (el) { t += num(el.value); });
    totalOut.value = fmt(t);
    diffOut.value = fmt(num(paid.value) - t);
  }
  items.forEach(function (el) { el.addEventListener('input', recalc); });
  paid.addEventListener('input', recalc);
  recalc();
})();
</script>
