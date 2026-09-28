<?php /* 채권 환불 신청 — 은행 select + 직접입력, 계좌, 예금주 */ ?>
<div class="greet">
  <p class="greet__at"><?= h($hh['complex_name']) ?> · <?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호</p>
  <h1 class="ttl">채권 환불 신청</h1>
  <p class="lead">
    <span class="s">국민주택채권 매입 후 남는 금액을 돌려드립니다.</span>
    <span class="s">환불 받으실 계좌를 등록해 주십시오.</span>
  </p>
</div>

<?php if ($saved): ?>
<div class="alert alert--ok" role="status">
  <b>환불 계좌가 등록되었습니다.</b>
  <span class="s">환불이 진행되면 아래 계좌로 보내드립니다.</span>
</div>
<?php elseif ($error !== ''): ?>
<p class="alert alert--err" role="alert"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($current !== null): ?>
<div class="card">
  <h2 class="sub">현재 등록된 계좌</h2>
  <dl class="kv">
    <div><dt>은행</dt><dd><?= h($current['bank']) ?></dd></div>
    <div><dt>계좌번호</dt><dd><?= h($current['account']) ?></dd></div>
    <div><dt>예금주</dt><dd><?= h($current['holder']) ?></dd></div>
    <?php if ($current['refund_date'] !== null): ?>
    <div><dt>환불일</dt><dd><?= h($current['refund_date']) ?></dd></div>
    <?php endif; ?>
    <div><dt>등록일</dt><dd><?= h(substr($current['created_at'], 0, 10)) ?></dd></div>
  </dl>
</div>
<?php endif; ?>

<form method="post" action="/refund" class="frm card">
  <?= csrf_field() ?>
  <h2 class="sub"><?= $current !== null ? '새 계좌로 바꾸기' : '계좌 등록' ?></h2>
  <div class="frm__field">
    <label for="inBank">은행</label>
    <select id="inBank" name="bank" required
            onchange="document.getElementById('fBankEtc').hidden = this.value !== '직접입력';">
      <option value="">은행을 선택해 주십시오</option>
      <?php foreach ($banks as $b): ?>
      <option value="<?= h($b) ?>"><?= h($b) ?></option>
      <?php endforeach; ?>
      <option value="직접입력">직접입력</option>
    </select>
  </div>
  <div class="frm__field" id="fBankEtc" hidden>
    <label for="inBankEtc">은행명 직접입력</label>
    <input type="text" id="inBankEtc" name="bank_etc" maxlength="30" placeholder="은행명">
  </div>
  <div class="frm__field">
    <label for="inAccount">계좌번호</label>
    <input type="text" id="inAccount" name="account" inputmode="numeric" maxlength="30"
           placeholder="숫자와 - 만 넣어 주십시오" required>
  </div>
  <div class="frm__field">
    <label for="inHolder">예금주</label>
    <input type="text" id="inHolder" name="holder" maxlength="30" placeholder="예금주 성함" required>
  </div>
  <button type="submit" class="btn btn--fill btn--full">계좌 저장</button>
  <p class="frm__lock">입력하신 계좌번호는 암호화해 보관하며, 채권 환불에만 씁니다.</p>
</form>
