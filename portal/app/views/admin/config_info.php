<h1 class="adm-ttl">기본 정보 설정</h1>
<p class="adm-lead">상호·대표자·연락처 등 포털 공통 정보를 관리합니다.</p>

<?php if ($saved): ?>
<div class="alert alert--ok"><b>저장했습니다</b>기본 정보가 갱신됐습니다.</div>
<?php endif; ?>

<form method="post" action="/admin/config">
  <?= csrf_field() ?>
  <div class="adm-sec">
    <h2>회사 정보</h2>
    <div class="adm-grid">
      <?php foreach ($fields as $key => $label): ?>
      <div class="adm-field"<?= $key === 'firm_address' ? ' style="grid-column:1/-1"' : '' ?>>
        <label for="f-<?= h($key) ?>"><?= h($label) ?></label>
        <input type="text" id="f-<?= h($key) ?>" name="<?= h($key) ?>" value="<?= h($values[$key] ?? '') ?>">
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <button type="submit" class="btn btn--fill">저장</button>
</form>

<div class="alert alert--info" style="margin-top:18px">
  <b>운영 정보 · SMS 설정 · 팝업 관리</b>
  기존 화면의 나머지 설정 묶음은 3단계에서 옮겨 옵니다. SMS 발신번호는 사전등록 번호(1899-4252)로 고정돼 있습니다.
</div>
