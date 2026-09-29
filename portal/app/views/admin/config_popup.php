<h1 class="adm-ttl">팝업 관리</h1>
<p class="adm-lead">손님이 로그인한 뒤 홈 화면에 띄우는 알림 팝업입니다.</p>

<?php if ($saved): ?>
<div class="alert alert--ok"><b>저장했습니다</b>
  지금 상태:
  <?= $active !== null ? '노출 중입니다. 손님 홈에서 바로 보입니다.' : '노출되지 않습니다 (사용 꺼짐 또는 기간 밖).' ?>
</div>
<?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<form method="post" action="/admin/config/popup">
  <?= csrf_field() ?>
  <div class="adm-sec">
    <h2>팝업 내용</h2>
    <div class="adm-field">
      <label for="p-title">제목</label>
      <input type="text" id="p-title" name="popup_title" maxlength="100" value="<?= h($values['popup_title'] ?? '') ?>">
    </div>
    <div class="adm-field" style="margin-top:12px">
      <label for="p-body">내용 (여러 줄 가능)</label>
      <textarea id="p-body" name="popup_body" style="min-height:110px"><?= h($values['popup_body'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="adm-sec">
    <h2>노출 설정</h2>
    <div class="adm-grid" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr))">
      <div class="adm-field">
        <label for="p-start">노출 시작일 (비우면 즉시)</label>
        <input type="date" id="p-start" name="popup_start" value="<?= h($values['popup_start'] ?? '') ?>">
      </div>
      <div class="adm-field">
        <label for="p-end">노출 종료일 (비우면 계속)</label>
        <input type="date" id="p-end" name="popup_end" value="<?= h($values['popup_end'] ?? '') ?>">
      </div>
      <div class="adm-field">
        <label>사용 여부</label>
        <label style="display:flex;align-items:center;gap:6px;font-weight:500;padding:10px 0">
          <input type="checkbox" name="popup_enabled" value="1"<?= ($values['popup_enabled'] ?? '') === '1' ? ' checked' : '' ?>>
          팝업 사용
        </label>
      </div>
    </div>
    <div class="alert alert--info" style="margin:14px 0 0">
      <b>노출 조건</b>
      「팝업 사용」이 켜져 있고 노출기간(양끝 포함) 안일 때만 손님 홈에 나옵니다. 기간이 지나면 자동으로 숨겨집니다.
    </div>
  </div>

  <button type="submit" class="btn btn--fill">저장</button>
</form>
