<h1 class="adm-ttl">SMS 발송</h1>
<p class="adm-lead">아파트 단위 또는 검색 결과 대상으로 문자를 보냅니다. 단문 90바이트, 장문 2,000바이트.</p>

<div class="alert alert--warn">
  <b>문자 발송 업체 연동 전 — 대기 목록에 저장됩니다</b>
  지금은 실제 문자가 나가지 않습니다. 저장된 건은 「SMS 발송 내역」에서 상태 「대기」로 확인합니다.
</div>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<form method="post" action="/admin/member/sms">
  <?= csrf_field() ?>
  <div class="adm-sec">
    <h2>보낼 내용</h2>
    <div class="adm-field">
      <label for="sms-body">내용</label>
      <textarea id="sms-body" name="body" style="min-height:120px" required></textarea>
    </div>
    <p class="sms-count" style="margin:10px 0 0">
      <b id="sms-bytes">0</b> / 2,000 바이트 —
      <span id="sms-type">단문(SMS)</span>
      <span>(90바이트 초과 시 장문 LMS 로 저장)</span>
    </p>
  </div>

  <div class="adm-sec">
    <h2>발신번호 · 대상</h2>
    <div class="adm-grid" style="grid-template-columns: 180px 1.6fr 120px 1fr">
      <div class="adm-field">
        <label>발신번호 (사전등록)</label>
        <input type="text" value="1899-4252" readonly style="background:var(--bg)">
      </div>
      <div class="adm-field">
        <label for="sms-complex">대상 아파트</label>
        <select id="sms-complex" name="complex_id" required>
          <option value="">선택</option>
          <?php foreach ($complexes as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adm-field">
        <label for="sms-dong">동 (선택)</label>
        <input type="text" id="sms-dong" name="dong" placeholder="비우면 전체">
      </div>
      <div class="adm-field">
        <label for="sms-name">이름 (선택)</label>
        <input type="text" id="sms-name" name="name" placeholder="비우면 전체">
      </div>
    </div>
    <div class="alert alert--info" style="margin:14px 0 0">
      <b>대상 계산</b>
      동·이름을 비우면 아파트 전체, 넣으면 검색 결과가 대상입니다. 핸드폰 번호가 있는 명의인만 셉니다(같은 번호는 1건).
    </div>
  </div>

  <button type="submit" class="btn btn--fill">대기 목록에 저장</button>
</form>

<script>
(function () {
  var ta = document.getElementById('sms-body');
  var out = document.getElementById('sms-bytes');
  var type = document.getElementById('sms-type');
  function euckrBytes(s) {           // 한글 2바이트 기준 (기존 시스템과 동일)
    var b = 0;
    for (var i = 0; i < s.length; i++) b += s.charCodeAt(i) > 127 ? 2 : 1;
    return b;
  }
  function update() {
    var b = euckrBytes(ta.value);
    out.textContent = b.toLocaleString();
    type.textContent = b <= 90 ? '단문(SMS)' : '장문(LMS)';
    out.style.color = b > 2000 ? 'var(--warn)' : 'var(--navy)';
  }
  ta.addEventListener('input', update);
  update();
})();
</script>
