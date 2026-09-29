<h1 class="adm-ttl">운영 정보 설정</h1>
<p class="adm-lead">상담 시간·휴무 등 손님 홈에 보여줄 운영 정보를 관리합니다.</p>

<?php if ($saved): ?>
<div class="alert alert--ok"><b>저장했습니다</b>손님 홈 화면에 바로 반영됩니다.</div>
<?php endif; ?>

<form method="post" action="/admin/config/ops">
  <?= csrf_field() ?>
  <div class="adm-sec">
    <h2>운영 시간</h2>
    <div class="adm-grid">
      <?php foreach ($fields as $key => $label): ?>
      <div class="adm-field">
        <label for="f-<?= h($key) ?>"><?= h($label) ?></label>
        <input type="text" id="f-<?= h($key) ?>" name="<?= h($key) ?>" value="<?= h($values[$key] ?? '') ?>"
               placeholder="<?= $key === 'ops_hours' ? '예: 평일 09:00 ~ 18:00' : ($key === 'ops_lunch' ? '예: 12:00 ~ 13:00' : '예: 토·일·공휴일 휴무') ?>">
      </div>
      <?php endforeach; ?>
    </div>
    <div class="adm-field" style="margin-top:12px">
      <label for="f-ops_notice">손님 홈 안내문 (여러 줄 가능)</label>
      <textarea id="f-ops_notice" name="ops_notice" style="min-height:90px"
                placeholder="예: 등기 관련 문의는 1899-4252 로 연락 주십시오."><?= h($values['ops_notice'] ?? '') ?></textarea>
    </div>
  </div>
  <button type="submit" class="btn btn--fill">저장</button>
</form>

<div class="alert alert--info" style="margin-top:18px">
  <b>비워 두면 숨겨집니다</b>
  값이 있는 항목만 손님 홈 하단의 「이용 안내」 카드에 나옵니다.
</div>
