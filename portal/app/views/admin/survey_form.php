<?php /* 설문/등기접수/위임장 등록·수정 — 기본 정보 + 문항 빌더 */
$base = $typeInfo['base'];
$isAccept = $surveyType === 'accept';
$isEdit = $survey !== null && (int)($survey['id'] ?? 0) > 0;
$qtypeLabels = survey_qtype_labels($surveyType);
?>
<h1 class="adm-ttl"><?= h($typeInfo['label']) ?> <?= $isEdit ? '조회/수정' : '등록' ?></h1>
<p class="adm-lead">
  기본 정보를 적고 아래에서 문항을 만듭니다. 저장 후 목록의 「참여대상자」에서
  대상 단지를 골라야 손님 화면에 나옵니다.
</p>

<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<form method="post" action="<?= h($base) ?>/form<?= $isEdit ? '?id=' . (int)$survey['id'] : '' ?>" id="sv-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $isEdit ? (int)$survey['id'] : 0 ?>">

  <div class="adm-sec">
    <h2>기본 정보</h2>
    <div class="adm-grid" style="grid-template-columns: 2fr 1fr">
      <div class="adm-field">
        <label for="sv-title"><?= h($typeInfo['label']) ?> 제목 *</label>
        <input type="text" id="sv-title" name="title" maxlength="200" required
               value="<?= h((string)($survey['title'] ?? '')) ?>">
      </div>
      <div class="adm-field">
        <label for="sv-org">주최</label>
        <input type="text" id="sv-org" name="organizer" maxlength="100"
               placeholder="예: 법무법인 제이엘" value="<?= h((string)($survey['organizer'] ?? '')) ?>">
      </div>
    </div>
    <div class="adm-field" style="margin-top:12px">
      <label for="sv-desc">추가설명 (손님 참여 화면 상단에 나옵니다)</label>
      <textarea id="sv-desc" name="description" style="min-height:90px"><?= h((string)($survey['description'] ?? '')) ?></textarea>
    </div>
    <div class="adm-grid" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); margin-top:12px">
      <div class="adm-field">
        <label for="sv-start">시작일<?= $isAccept ? ' *' : ' (선택)' ?></label>
        <input type="date" id="sv-start" name="starts_on" value="<?= h((string)($survey['starts_on'] ?? '')) ?>"<?= $isAccept ? ' required' : '' ?>>
      </div>
      <div class="adm-field">
        <label for="sv-end">종료일<?= $isAccept ? ' *' : ' (선택)' ?></label>
        <input type="date" id="sv-end" name="ends_on" value="<?= h((string)($survey['ends_on'] ?? '')) ?>"<?= $isAccept ? ' required' : '' ?>>
      </div>
      <?php if ($isAccept): ?>
      <div class="adm-field">
        <label>핸드폰 확인</label>
        <label style="display:flex;align-items:center;gap:6px;font-weight:500;padding:10px 0">
          <input type="checkbox" name="phone_required" value="1"<?= !empty($survey['phone_required']) ? ' checked' : '' ?>>
          제출 때 핸드폰 번호를 꼭 받는다
        </label>
      </div>
      <?php endif; ?>
      <div class="adm-field">
        <label>진행 상태</label>
        <label style="display:flex;align-items:center;gap:6px;font-weight:500;padding:10px 0">
          <input type="checkbox" name="is_open" value="1"<?= $survey === null || !empty($survey['is_open']) ? ' checked' : '' ?>>
          진행중 (끄면 손님 화면에서 숨김)
        </label>
      </div>
    </div>
    <?php if ($isAccept): ?>
    <div class="alert alert--info" style="margin:14px 0 0">
      <b>등기접수는 기간이 기준입니다</b>
      시작일~종료일(양끝 포함) 안에서만 손님이 제출할 수 있고, 기간 밖이면 자동으로 막힙니다.
    </div>
    <?php endif; ?>
  </div>

  <div class="adm-sec">
    <h2>문항 빌더</h2>
    <div class="alert alert--info" style="margin:0 0 14px">
      <b>문항 유형</b>
      객관식(보기 중 선택) · 주관식(자유 입력) · 설명글(답 없이 안내만)<?= $isAccept ? ' · 핸드폰(번호 형식 검증)' : '' ?>.
      객관식 보기는 한 줄에 하나씩 적습니다. 순서대로 손님 화면에 나옵니다.
    </div>

    <div id="q-list">
      <?php foreach ($questions as $i => $q): ?>
      <div class="adm-sec q-row" style="background:var(--bg); margin-bottom:12px">
        <input type="hidden" name="q_id[<?= $i ?>]" value="<?= (int)($q['id'] ?? 0) ?>">
        <div class="adm-grid" style="grid-template-columns: 150px 1fr 110px 90px">
          <div class="adm-field">
            <label>유형</label>
            <select name="q_type[<?= $i ?>]" class="q-type">
              <?php foreach ($qtypeLabels as $val => $lbl): ?>
              <option value="<?= h($val) ?>"<?= ($q['qtype'] ?? 'choice') === $val ? ' selected' : '' ?>><?= h($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="adm-field">
            <label>문항 제목</label>
            <input type="text" name="q_title[<?= $i ?>]" maxlength="500" value="<?= h((string)($q['title'] ?? '')) ?>">
          </div>
          <div class="adm-field">
            <label>필수</label>
            <label class="q-req" style="display:flex;align-items:center;gap:6px;font-weight:500;padding:10px 0">
              <input type="checkbox" name="q_required[<?= $i ?>]" value="1"<?= !empty($q['required']) ? ' checked' : '' ?>> 필수
            </label>
          </div>
          <div class="adm-field">
            <label>&nbsp;</label>
            <button type="button" class="btn btn--xs btn--danger q-del">지우기</button>
          </div>
        </div>
        <div class="adm-field q-opts" style="margin-top:10px">
          <label>객관식 보기 (한 줄에 하나, 2개 이상)</label>
          <textarea name="q_options[<?= $i ?>]" style="min-height:80px"><?= h(implode("\n", $q['options'] ?? [])) ?></textarea>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="margin:0"><button type="button" class="btn btn--sm" id="q-add">문항 추가</button></p>
  </div>

  <button type="submit" class="btn btn--fill">저장</button>
  <a class="btn" href="<?= h($base) ?>">목록으로</a>
</form>

<template id="q-tpl">
  <div class="adm-sec q-row" style="background:var(--bg); margin-bottom:12px">
    <input type="hidden" name="q_id[@I@]" value="0">
    <div class="adm-grid" style="grid-template-columns: 150px 1fr 110px 90px">
      <div class="adm-field">
        <label>유형</label>
        <select name="q_type[@I@]" class="q-type">
          <?php foreach ($qtypeLabels as $val => $lbl): ?>
          <option value="<?= h($val) ?>"><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adm-field">
        <label>문항 제목</label>
        <input type="text" name="q_title[@I@]" maxlength="500">
      </div>
      <div class="adm-field">
        <label>필수</label>
        <label class="q-req" style="display:flex;align-items:center;gap:6px;font-weight:500;padding:10px 0">
          <input type="checkbox" name="q_required[@I@]" value="1"> 필수
        </label>
      </div>
      <div class="adm-field">
        <label>&nbsp;</label>
        <button type="button" class="btn btn--xs btn--danger q-del">지우기</button>
      </div>
    </div>
    <div class="adm-field q-opts" style="margin-top:10px">
      <label>객관식 보기 (한 줄에 하나, 2개 이상)</label>
      <textarea name="q_options[@I@]" style="min-height:80px"></textarea>
    </div>
  </div>
</template>

<script>
(function () {
  var list = document.getElementById('q-list');
  var tpl = document.getElementById('q-tpl');
  var next = <?= count($questions) ?>;

  function refreshRow(row) {              // 유형에 따라 보기/필수 칸을 보이거나 숨긴다
    var type = row.querySelector('.q-type').value;
    row.querySelector('.q-opts').style.display = type === 'choice' ? '' : 'none';
    row.querySelector('.q-req').style.visibility = type === 'note' ? 'hidden' : '';
  }
  function bindRow(row) {
    row.querySelector('.q-type').addEventListener('change', function () { refreshRow(row); });
    row.querySelector('.q-del').addEventListener('click', function () { row.remove(); });
    refreshRow(row);
  }
  function addRow() {
    var html = tpl.innerHTML.replace(/@I@/g, String(next++));
    var box = document.createElement('div');
    box.innerHTML = html;
    var row = box.firstElementChild;
    list.appendChild(row);
    bindRow(row);
    return row;
  }
  document.getElementById('q-add').addEventListener('click', addRow);
  Array.prototype.forEach.call(list.querySelectorAll('.q-row'), bindRow);
  if (list.children.length === 0) addRow();   // 새 등록은 빈 문항 하나로 시작
})();
</script>
