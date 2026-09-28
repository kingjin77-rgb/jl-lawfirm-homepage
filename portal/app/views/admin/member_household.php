<h1 class="adm-ttl">세대/소유자 관리</h1>
<p class="adm-lead">세대(동·호)와 명의인(공동명의 포함)을 검색·등록·수정합니다. 삭제는 admin 권한만 됩니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<form class="adm-filter" method="get" action="/admin/member/household">
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
    <button type="submit" class="btn btn--fill btn--sm">검색</button>
    <a class="btn btn--sm" href="/admin/member/household?new=1">새 세대 등록</a>
  </div>
</form>

<?php if ($showForm): ?>
<div class="adm-sec" id="hh-form">
  <h2><?= $edit !== null ? '세대 수정 (#' . (int)$edit['id'] . ')' : '새 세대 등록' ?></h2>
  <form method="post" action="/admin/member/household">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="adm-grid" style="grid-template-columns: 2fr 1fr 1fr; margin-bottom:14px">
      <div class="adm-field"><label>아파트</label>
        <select name="complex_id" required>
          <option value="">선택</option>
          <?php foreach ($complexes as $c): ?>
          <option value="<?= (int)$c['id'] ?>"<?= (int)($edit['complex_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adm-field"><label>동</label><input type="text" name="dong" required value="<?= h($edit['dong'] ?? '') ?>"></div>
      <div class="adm-field"><label>호수</label><input type="text" name="ho" required value="<?= h($edit['ho'] ?? '') ?>"></div>
    </div>

    <div class="owner-row owner-head" aria-hidden="true">
      <span>성명</span><span>생년월일 6자리</span><span>핸드폰</span><span>구분</span><span></span>
    </div>
    <?php
    $ownerRows = $edit['owners'] ?? [];
    for ($i = 0; $i < max(2, count($ownerRows) + 1); $i++):
        $o = $ownerRows[$i] ?? null; ?>
    <div class="owner-row">
      <input type="text" name="owner_name[<?= $i ?>]" placeholder="성명" value="<?= h($o['name'] ?? '') ?>">
      <input type="text" name="owner_birth6[<?= $i ?>]" placeholder="예: 900101" maxlength="6" inputmode="numeric" value="<?= h($o['birth6'] ?? '') ?>">
      <input type="text" name="owner_phone[<?= $i ?>]" placeholder="핸드폰" value="<?= h($o['phone'] ?? '') ?>">
      <span class="chk"><?= $i === 0 ? '대표 명의인' : '공동명의' ?></span>
      <span></span>
    </div>
    <?php endfor; ?>
    <div class="alert alert--info" style="margin:12px 0">
      <b>공동명의 입력</b>
      두 번째 줄부터가 공동명의인입니다. 이름을 비우면 그 줄은 저장되지 않습니다.
    </div>
    <p style="margin:0; display:flex; gap:10px">
      <button type="submit" class="btn btn--fill btn--sm"><?= $edit !== null ? '수정 저장' : '등록' ?></button>
      <?php if ($edit !== null): ?>
      <a class="btn btn--sm" href="/admin/registration/form?hid=<?= (int)$edit['id'] ?>">등기진행 상세로</a>
      <?php endif; ?>
    </p>
  </form>

  <?php if ($edit !== null && ($staff['role'] ?? '') === 'admin'): ?>
  <form method="post" action="/admin/member/household" style="margin-top:16px; padding-top:14px; border-top:1px solid var(--line); display:flex; gap:12px; align-items:center; flex-wrap:wrap"
        onsubmit="return confirm('이 세대와 등기진행·비용·주소·환불 기록이 전부 삭제됩니다. 계속할까요?')">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="delete">
    <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
    <label style="font-size:15px; display:inline-flex; align-items:center; gap:6px">
      <input type="checkbox" name="confirm" value="1"> 삭제 내용을 확인했습니다
    </label>
    <button type="submit" class="btn btn--sm btn--danger">세대 삭제 (admin)</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>번호</th><th>아파트명</th><th class="ctr">동</th><th class="ctr">호수</th><th>명의인 (생년월일)</th><th>핸드폰</th><th>수정일</th><th class="ctr">관리</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?><tr><td colspan="8" class="ctr">조건에 맞는 세대가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="num"><?= (int)$r['id'] ?></td>
      <td class="nowrap"><?= h($r['complex_name']) ?></td>
      <td class="ctr"><?= h($r['dong']) ?></td>
      <td class="ctr"><?= h($r['ho']) ?></td>
      <td>
        <?php foreach ($r['owners'] as $o): ?>
        <span class="nowrap" style="display:block"><?= h($o['name']) ?> (<?= h($o['birth6']) ?>)<?= $o['is_primary'] ? '' : ' · 공동' ?></span>
        <?php endforeach; ?>
      </td>
      <td>
        <?php foreach ($r['owners'] as $o): ?>
        <span class="nowrap" style="display:block"><?= h($o['phone']) ?: '—' ?></span>
        <?php endforeach; ?>
      </td>
      <td class="nowrap"><?= h(substr((string)$r['updated_at'], 0, 10)) ?></td>
      <td class="ctr nowrap">
        <a class="btn btn--xs" href="/admin/member/household?edit=<?= (int)$r['id'] ?>#hh-form">수정</a>
        <a class="btn btn--xs" href="/admin/registration/form?hid=<?= (int)$r['id'] ?>">등기상세</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <?php
  $qsBase = array_filter(['complex_id' => $f['complex_id'] ?: null, 'dong' => $f['dong'] ?: null,
      'ho' => $f['ho'] ?: null, 'name' => $f['name'] ?: null], fn($v) => $v !== null);
  for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++):
      if ($p === $page): ?><strong><?= $p ?></strong>
      <?php else: ?><a href="/admin/member/household?<?= h(http_build_query($qsBase + ['page' => $p])) ?>"><?= $p ?></a>
      <?php endif;
  endfor; ?>
  <span class="pager__total">총 <?= number_format($total) ?>세대 · <?= $page ?>/<?= $pages ?>쪽</span>
</div>
