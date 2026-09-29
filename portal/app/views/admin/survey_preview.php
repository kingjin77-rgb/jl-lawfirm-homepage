<?php /* 미리보기 — 손님 참여 폼과 같은 배치 (제출 없음) */
$base = $typeInfo['base'];
?>
<h1 class="adm-ttl">미리보기 — <?= h($survey['title']) ?></h1>
<p class="adm-lead">손님 참여 화면과 같은 배치입니다. 여기서는 제출되지 않습니다.</p>

<div class="adm-sec" style="max-width:720px">
  <h2><?= h($survey['title']) ?></h2>
  <?php if (trim((string)$survey['organizer']) !== ''): ?>
  <p style="margin:0 0 8px;font-size:15px;color:var(--ink-soft)">주최: <?= h($survey['organizer']) ?></p>
  <?php endif; ?>
  <?php if (!empty($survey['starts_on']) || !empty($survey['ends_on'])): ?>
  <p style="margin:0 0 8px;font-size:15px;color:var(--ink-soft)">
    기간: <?= h((string)($survey['starts_on'] ?? '')) ?: '제한 없음' ?> ~ <?= h((string)($survey['ends_on'] ?? '')) ?: '제한 없음' ?>
  </p>
  <?php endif; ?>
  <?php if (trim((string)($survey['description'] ?? '')) !== ''): ?>
  <div class="alert alert--info"><b>안내</b><?= nl2br(h((string)$survey['description'])) ?></div>
  <?php endif; ?>

  <?php if (!empty($survey['phone_required'])): ?>
  <div class="adm-field" style="margin:0 0 16px">
    <label>핸드폰 번호 * <span style="font-weight:500">(제출 확인용)</span></label>
    <input type="text" placeholder="010-1234-5678" disabled>
  </div>
  <?php endif; ?>

  <?php if ($questions === []): ?>
  <div class="alert alert--warn" style="margin:0"><b>문항이 없습니다</b>「조회/수정」에서 문항을 만들어 주십시오.</div>
  <?php endif; ?>

  <?php foreach ($questions as $q): ?>
    <?php if ($q['qtype'] === 'note'): ?>
    <div class="alert alert--info"><b>안내</b><?= nl2br(h($q['title'])) ?></div>
    <?php else: ?>
    <div class="adm-field" style="margin:0 0 16px">
      <label><?= h($q['title']) ?><?= $q['required'] ? ' *' : '' ?></label>
      <?php if ($q['qtype'] === 'choice'): ?>
        <?php foreach ($q['options'] as $opt): ?>
        <label style="display:flex;align-items:center;gap:8px;font-weight:500;padding:4px 0">
          <input type="radio" disabled> <?= h($opt) ?>
        </label>
        <?php endforeach; ?>
      <?php elseif ($q['qtype'] === 'phone'): ?>
      <input type="text" placeholder="010-1234-5678" disabled>
      <?php else: ?>
      <textarea style="min-height:70px" disabled></textarea>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <button type="button" class="btn btn--fill" disabled>제출 (미리보기)</button>
</div>

<p><a class="btn" href="<?= h($base) ?>">목록으로</a>
   <a class="btn" href="<?= h($base) ?>/form?id=<?= (int)$survey['id'] ?>">조회/수정</a></p>
