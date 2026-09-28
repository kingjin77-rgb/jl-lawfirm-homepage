<h1 class="adm-ttl">FAQ 관리</h1>
<p class="adm-lead">손님 화면 「자주묻는질문」에 나오는 글을 관리합니다. 숨긴 글은 손님에게 보이지 않습니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<div class="adm-sec">
  <h2><?= $edit !== null ? 'FAQ 수정 (#' . (int)$edit['id'] . ')' : '새 FAQ 등록' ?></h2>
  <form method="post" action="/admin/config/faq">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="adm-grid" style="grid-template-columns: 160px 1fr 110px">
      <div class="adm-field">
        <label for="faq-cat">분류</label>
        <select id="faq-cat" name="category" required>
          <option value="">선택</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= h($c) ?>"<?= ($edit['category'] ?? '') === $c ? ' selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adm-field">
        <label for="faq-q">질문</label>
        <input type="text" id="faq-q" name="question" maxlength="300" required value="<?= h($edit['question'] ?? '') ?>">
      </div>
      <div class="adm-field">
        <label for="faq-sort">정렬</label>
        <input type="number" id="faq-sort" name="sort" value="<?= (int)($edit['sort'] ?? 0) ?>">
      </div>
    </div>
    <div class="adm-field" style="margin-top:12px">
      <label for="faq-a">답변</label>
      <textarea id="faq-a" name="answer" required><?= h($edit['answer'] ?? '') ?></textarea>
    </div>
    <p style="margin:14px 0 0; display:flex; gap:10px">
      <button type="submit" class="btn btn--fill btn--sm"><?= $edit !== null ? '수정 저장' : '등록' ?></button>
      <?php if ($edit !== null): ?><a class="btn btn--sm" href="/admin/config/faq">새 글 쓰기로</a><?php endif; ?>
    </p>
  </form>
</div>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>번호</th><th>분류</th><th>질문</th><th class="ctr">정렬</th><th class="ctr">노출</th><th class="ctr">관리</th></tr></thead>
    <tbody>
    <?php if ($faqs === []): ?>
    <tr><td colspan="6" class="ctr">등록된 FAQ 가 없습니다.</td></tr>
    <?php endif; ?>
    <?php foreach ($faqs as $f): ?>
    <tr>
      <td class="num"><?= (int)$f['id'] ?></td>
      <td class="nowrap"><?= h($f['category']) ?></td>
      <td><?= h($f['question']) ?></td>
      <td class="ctr"><?= (int)$f['sort'] ?></td>
      <td class="ctr"><?= $f['visible'] ? '<span class="mark-done">노출</span>' : '<span class="mark-wait">숨김</span>' ?></td>
      <td class="ctr nowrap">
        <a class="btn btn--xs" href="/admin/config/faq?edit=<?= (int)$f['id'] ?>">수정</a>
        <form method="post" action="/admin/config/faq" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
          <button type="submit" class="btn btn--xs"><?= $f['visible'] ? '숨기기' : '노출' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
