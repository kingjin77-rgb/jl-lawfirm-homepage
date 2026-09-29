<?php /* 손님 참여 폼 — 설문/등기접수/위임장 공용 */ ?>
<div class="greet">
  <p class="greet__at"><?= h($typeLabel) ?><?= trim((string)$survey['organizer']) !== '' ? ' · 주최 ' . h($survey['organizer']) : '' ?></p>
  <h1 class="ttl"><?= h($survey['title']) ?></h1>
  <?php if (!empty($survey['starts_on']) || !empty($survey['ends_on'])): ?>
  <p class="lead">
    <span class="s">참여 기간:
      <?= h((string)($survey['starts_on'] ?? '')) ?: '제한 없음' ?> ~ <?= h((string)($survey['ends_on'] ?? '')) ?: '제한 없음' ?></span>
  </p>
  <?php endif; ?>
</div>

<?php if ($saved): ?>
<div class="alert alert--ok" role="status">
  <b>제출되었습니다. 참여해 주셔서 고맙습니다.</b>
  <span class="s">내용을 고치고 싶으시면 아래에서 수정해 다시 제출하시면 됩니다.</span>
</div>
<?php elseif ($error !== ''): ?>
<p class="alert alert--err" role="alert"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!$isOpen): ?>
<div class="alert alert--warn" role="alert">
  <b>지금은 참여 기간이 아닙니다.</b>
  <span class="s">
    <?php if (!empty($survey['starts_on']) && date('Y-m-d') < $survey['starts_on']): ?>
    <?= h($survey['starts_on']) ?> 부터 참여하실 수 있습니다.
    <?php else: ?>
    참여가 마감되었습니다. 문의는 1899-4252 로 연락 주십시오.
    <?php endif; ?>
  </span>
</div>
<p><a class="btn" href="/home">처음으로 돌아가기</a></p>
<?php else: ?>

<?php if (trim((string)($survey['description'] ?? '')) !== ''): ?>
<div class="alert alert--info">
  <b>안내</b>
  <?= nl2br(h((string)$survey['description'])) ?>
</div>
<?php endif; ?>

<?php if ($existing !== null && !$saved): ?>
<div class="alert alert--info" role="status">
  <b>이미 제출하셨습니다 (<?= h(substr((string)$existing['submitted_at'], 0, 16)) ?>).</b>
  <span class="s">아래 내용을 고쳐 다시 제출하시면 마지막 내용으로 바뀝니다.</span>
</div>
<?php endif; ?>

<form method="post" action="/participate?id=<?= (int)$survey['id'] ?>" class="frm card">
  <?= csrf_field() ?>
  <input type="hidden" name="survey_id" value="<?= (int)$survey['id'] ?>">

  <?php if (!empty($survey['phone_required'])): ?>
  <div class="frm__field">
    <label for="inPhone">핸드폰 번호 * <span style="font-weight:500">(제출 확인용)</span></label>
    <input type="text" id="inPhone" name="response_phone" inputmode="tel" maxlength="13"
           placeholder="010-1234-5678" required value="<?= h((string)($existing['phone'] ?? '')) ?>">
  </div>
  <?php endif; ?>

  <?php foreach ($questions as $q): $qid = (int)$q['id']; $val = (string)($existing['answers'][$qid] ?? ''); ?>
    <?php if ($q['qtype'] === 'note'): ?>
    <div class="alert alert--info">
      <b>안내</b>
      <?= nl2br(h($q['title'])) ?>
    </div>
    <?php elseif ($q['qtype'] === 'choice'): ?>
    <fieldset class="frm__field" style="border:0;padding:0;margin:0 0 14px">
      <legend style="font-size:15px;font-weight:700;color:var(--navy);margin-bottom:6px">
        <?= h($q['title']) ?><?= $q['required'] ? ' *' : '' ?></legend>
      <?php foreach ($q['options'] as $j => $opt): ?>
      <label style="display:flex;align-items:center;gap:8px;padding:6px 0;font-size:16px">
        <input type="radio" name="q<?= $qid ?>" value="<?= h($opt) ?>"<?= $val === $opt ? ' checked' : '' ?><?= $q['required'] ? ' required' : '' ?>>
        <?= h($opt) ?>
      </label>
      <?php endforeach; ?>
    </fieldset>
    <?php elseif ($q['qtype'] === 'phone'): ?>
    <div class="frm__field">
      <label for="q-<?= $qid ?>"><?= h($q['title']) ?><?= $q['required'] ? ' *' : '' ?></label>
      <input type="text" id="q-<?= $qid ?>" name="q<?= $qid ?>" inputmode="tel" maxlength="13"
             placeholder="010-1234-5678"<?= $q['required'] ? ' required' : '' ?> value="<?= h($val) ?>">
    </div>
    <?php else: ?>
    <div class="frm__field">
      <label for="q-<?= $qid ?>"><?= h($q['title']) ?><?= $q['required'] ? ' *' : '' ?></label>
      <textarea id="q-<?= $qid ?>" name="q<?= $qid ?>" style="width:100%;font:inherit;font-size:16px;border:1px solid var(--line);border-radius:8px;padding:12px 14px;min-height:90px"<?= $q['required'] ? ' required' : '' ?>><?= h($val) ?></textarea>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <button type="submit" class="btn btn--fill btn--full"><?= $existing !== null ? '수정해서 다시 제출' : '제출' ?></button>
  <p class="frm__lock">제출하신 내용은 세대당 1건으로 보관되며, 다시 제출하면 마지막 내용으로 바뀝니다.</p>
</form>
<?php endif; ?>

<p style="margin-top:20px"><a class="btn" href="/home">처음으로 돌아가기</a></p>
