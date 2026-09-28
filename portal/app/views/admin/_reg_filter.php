<?php
/**
 * 등기진행 계열 목록 공용 필터 폼 — $f(필터), $complexes, $filterAction 필요.
 * $filterSteps=false 면 8단계 체크박스를 숨긴다(주소/환불 현황).
 */
$filterSteps = $filterSteps ?? true;
?>
<form class="adm-filter" method="get" action="<?= h($filterAction) ?>">
  <div class="adm-filter__row">
    <div class="adm-filter__f">
      <label>아파트</label>
      <select name="complex_id">
        <option value="0">전체</option>
        <?php foreach ($complexes as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= $f['complex_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="adm-filter__f adm-filter__f--sm"><label>동</label><input type="text" name="dong" value="<?= h($f['dong']) ?>"></div>
    <div class="adm-filter__f adm-filter__f--sm"><label>호수</label><input type="text" name="ho" value="<?= h($f['ho']) ?>"></div>
    <div class="adm-filter__f"><label>이름</label><input type="text" name="name" value="<?= h($f['name']) ?>"></div>
    <div class="adm-filter__f adm-filter__f--sm"><label>생년월일</label><input type="text" name="birth6" maxlength="6" inputmode="numeric" value="<?= h($f['birth6']) ?>"></div>
    <button type="submit" class="btn btn--fill btn--sm">검색</button>
  </div>
  <?php if ($filterSteps): ?>
  <div class="adm-filter__steps">
    <?php foreach (progress_step_names() as $n => $label): ?>
    <label><input type="checkbox" name="steps[]" value="<?= $n ?>"<?= in_array($n, $f['steps'], true) ? ' checked' : '' ?>>
      <?= $n ?>.<?= h($label) ?></label>
    <?php endforeach; ?>
    <span style="font-size:14px; color:var(--ink-soft)">(체크한 단계가 모두 완료된 세대만)</span>
  </div>
  <?php endif; ?>
</form>
