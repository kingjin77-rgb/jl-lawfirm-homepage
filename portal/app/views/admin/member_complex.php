<h1 class="adm-ttl">아파트 관리</h1>
<p class="adm-lead">단지(그룹)와 단지별 수납계좌를 관리합니다. 노출한 단지만 손님 로그인 화면에 나옵니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<div class="adm-sec">
  <h2><?= $edit !== null ? '아파트 수정 (#' . (int)$edit['id'] . ')' : '새 아파트 등록' ?></h2>
  <form method="post" action="/admin/member/complex">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="adm-grid" style="grid-template-columns: 2fr 1fr 1.4fr 1fr 90px">
      <div class="adm-field"><label>아파트명</label>
        <input type="text" name="name" required value="<?= h($edit['name'] ?? '') ?>"></div>
      <div class="adm-field"><label>은행명</label>
        <input type="text" name="bank_name" value="<?= h($edit['bank_name'] ?? '') ?>"></div>
      <div class="adm-field"><label>계좌번호 (수납계좌)</label>
        <input type="text" name="bank_account" value="<?= h($edit['bank_account'] ?? '') ?>"></div>
      <div class="adm-field"><label>예금주</label>
        <input type="text" name="bank_holder" value="<?= h($edit['bank_holder'] ?? '') ?>"></div>
      <div class="adm-field"><label>정렬</label>
        <input type="number" name="sort" value="<?= (int)($edit['sort'] ?? 0) ?>"></div>
    </div>
    <p style="margin:12px 0 0; display:flex; gap:14px; align-items:center; flex-wrap:wrap">
      <label style="font-size:15px; display:inline-flex; align-items:center; gap:6px">
        <input type="checkbox" name="exposed" value="1"<?= ($edit === null || $edit['exposed']) ? ' checked' : '' ?>>
        손님 로그인 화면에 노출
      </label>
      <button type="submit" class="btn btn--fill btn--sm"><?= $edit !== null ? '수정 저장' : '등록' ?></button>
      <?php if ($edit !== null): ?><a class="btn btn--sm" href="/admin/member/complex">새 등록으로</a><?php endif; ?>
    </p>
  </form>
</div>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr>
      <th>번호</th><th>아파트명</th><th class="ctr">회원수</th><th class="ctr">세대수</th><th class="ctr">등기진행수</th>
      <th>은행명</th><th>계좌번호</th><th>예금주</th><th class="ctr">노출</th><th class="ctr">관리</th>
    </tr></thead>
    <tbody>
    <?php if ($list === []): ?><tr><td colspan="10" class="ctr">등록된 아파트가 없습니다.</td></tr><?php endif; ?>
    <?php foreach ($list as $c): ?>
    <tr>
      <td class="num"><?= (int)$c['id'] ?></td>
      <td><b><?= h($c['name']) ?></b></td>
      <td class="ctr num"><?= number_format((int)$c['member_cnt']) ?></td>
      <td class="ctr num"><?= number_format((int)$c['household_cnt']) ?></td>
      <td class="ctr num"><?= number_format((int)$c['progress_cnt']) ?></td>
      <td class="nowrap"><?= h($c['bank_name']) ?></td>
      <td class="nowrap"><?= h($c['bank_account']) ?></td>
      <td class="nowrap"><?= h($c['bank_holder']) ?></td>
      <td class="ctr"><?= $c['exposed'] ? '<span class="mark-done">노출</span>' : '<span class="mark-wait">숨김</span>' ?></td>
      <td class="ctr"><a class="btn btn--xs" href="/admin/member/complex?edit=<?= (int)$c['id'] ?>">수정</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
