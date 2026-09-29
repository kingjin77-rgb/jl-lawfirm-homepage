<?php /* 참여자 SMS — member_sms 와 같은 방식 (대기 저장만) */
$base = $typeInfo['base'];
?>
<h1 class="adm-ttl">참여자 SMS — <?= h($typeInfo['label']) ?></h1>
<p class="adm-lead"><?= h($typeInfo['label']) ?> 건을 골라 참여/불참/전체 세대에 문자를 보냅니다. 단문 90바이트, 장문 2,000바이트.</p>

<div class="alert alert--warn">
  <b>문자 발송 업체 연동 전 — 대기 목록에 저장됩니다</b>
  지금은 실제 문자가 나가지 않습니다. 저장된 건은 「인적사항 관리 → SMS 발송 내역」에서 상태 「대기」로 확인합니다.
</div>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><b>저장했습니다</b><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<?php if ($surveys === []): ?>
<div class="alert alert--info">
  <b>등록된 <?= h($typeInfo['label']) ?>이(가) 없습니다</b>
  먼저 「<?= h($typeInfo['label']) ?> 등록」에서 만들고 참여대상자를 고르십시오.
</div>
<?php else: ?>
<form method="post" action="<?= h($base) ?>/sms">
  <?= csrf_field() ?>
  <div class="adm-sec">
    <h2>대상</h2>
    <div class="adm-grid" style="grid-template-columns: 1.6fr 1fr 180px">
      <div class="adm-field">
        <label for="sms-survey">대상 <?= h($typeInfo['label']) ?></label>
        <select id="sms-survey" name="survey_id" required>
          <option value="">선택</option>
          <?php foreach ($surveys as $s): ?>
          <option value="<?= (int)$s['id'] ?>"<?= $selId === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adm-field">
        <label>받을 사람</label>
        <div style="display:flex;gap:14px;flex-wrap:wrap;padding:10px 0">
          <label style="display:flex;align-items:center;gap:6px;font-weight:500">
            <input type="radio" name="audience" value="joined"> 참여 세대</label>
          <label style="display:flex;align-items:center;gap:6px;font-weight:500">
            <input type="radio" name="audience" value="absent"> 불참 세대</label>
          <label style="display:flex;align-items:center;gap:6px;font-weight:500">
            <input type="radio" name="audience" value="all" checked> 대상 단지 전체</label>
        </div>
      </div>
      <div class="adm-field">
        <label>발신번호 (사전등록)</label>
        <input type="text" value="1899-4252" readonly style="background:var(--bg)">
      </div>
    </div>
    <div class="alert alert--info" style="margin:14px 0 0">
      <b>대상 계산</b>
      핸드폰 번호가 있는 명의인 기준이며 같은 번호는 1건으로 셉니다.
      참여 세대는 제출 때 적은 핸드폰 번호도 포함합니다.
    </div>
  </div>

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
<?php endif; ?>
