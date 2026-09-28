<?php /* 권리증 수령 주소 — 현재 등록분 + 저장 폼 */ ?>
<div class="greet">
  <p class="greet__at"><?= h($hh['complex_name']) ?> · <?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호</p>
  <h1 class="ttl">권리증 수령 주소</h1>
  <p class="lead">
    <span class="s">등기가 끝나면 권리증(등기필증)을 이 주소로 보내드립니다.</span>
    <span class="s">이사 예정이시면 받으실 주소로 등록해 주십시오.</span>
  </p>
</div>

<?php if ($saved): ?>
<div class="alert alert--ok" role="status">
  <b>주소가 등록되었습니다.</b>
  <span class="s">권리증은 아래 주소로 보내드립니다.</span>
</div>
<?php elseif ($error !== ''): ?>
<p class="alert alert--err" role="alert"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($current !== null): ?>
<div class="card">
  <h2 class="sub">현재 등록된 주소</h2>
  <dl class="kv">
    <div><dt>우편번호</dt><dd><?= h($current['zip']) ?></dd></div>
    <div><dt>주소</dt><dd><?= h($current['addr1']) ?> <?= h($current['addr2']) ?></dd></div>
    <div><dt>등록일</dt><dd><?= h(substr($current['created_at'], 0, 10)) ?></dd></div>
  </dl>
</div>
<?php endif; ?>

<form method="post" action="/address" class="frm card">
  <?= csrf_field() ?>
  <h2 class="sub"><?= $current !== null ? '새 주소로 바꾸기' : '주소 등록' ?></h2>
  <div class="frm__field frm__field--zip">
    <label for="inZip">우편번호</label>
    <input type="text" id="inZip" name="zip" inputmode="numeric" maxlength="5" placeholder="12345" required>
  </div>
  <div class="frm__field">
    <label for="inAddr1">주소</label>
    <input type="text" id="inAddr1" name="addr1" placeholder="도로명 주소" maxlength="200" required>
  </div>
  <div class="frm__field">
    <label for="inAddr2">상세주소</label>
    <input type="text" id="inAddr2" name="addr2" placeholder="동·호수 등" maxlength="200">
  </div>
  <button type="submit" class="btn btn--fill btn--full">주소 저장</button>
  <p class="frm__lock">입력하신 주소는 암호화해 보관하며, 권리증 발송에만 씁니다.</p>
</form>
