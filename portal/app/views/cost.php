<?php /* 등기비용 내역서 — 0원 항목 숨김, 합계는 항상. 기록 없으면 준비중 */ ?>
<div class="greet">
  <p class="greet__at"><?= h($hh['complex_name']) ?> · <?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호</p>
  <h1 class="ttl">등기비용 내역서</h1>
</div>

<?php if ($cost === null): ?>
<div class="alert alert--info">
  <b>준비중입니다.</b>
  <span class="s">등기비용이 계산되면 이곳에 항목별로 표시됩니다.</span>
</div>
<?php else: ?>

<table class="bill">
  <tbody>
    <?php foreach ($items as $it): ?>
    <tr><th><?= h($it['label']) ?></th><td><?= h(won($it['amount'])) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="bill__sum"><th>합계</th><td><?= h(won((int)$cost['total'])) ?></td></tr>
    <tr><th>입금액</th><td><?= h(won((int)$cost['paid_amount'])) ?></td></tr>
    <tr class="bill__diff<?= (int)$cost['diff'] > 0 ? ' is-due' : '' ?>">
      <th>차액</th><td><?= h(won((int)$cost['diff'])) ?></td>
    </tr>
  </tfoot>
</table>

<?php if ((int)$cost['diff'] > 0): ?>
<div class="alert alert--warn">
  <b>아직 <?= h(won((int)$cost['diff'])) ?>이 더 입금되어야 합니다.</b>
  <?php if ($hh['bank_account'] !== ''): ?>
  <span class="s">입금 계좌: <?= h($hh['bank_name']) ?> <?= h($hh['bank_account']) ?> (예금주 <?= h($hh['bank_holder']) ?>)</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="note">
  <b>항목 안내</b>
  <ul>
    <li><span class="s"><b>취득세와 채권</b>은 나라에 내는 세금과 국민주택채권입니다.</span></li>
    <li><span class="s"><b>보수료와 부가세</b>가 법무법인 수수료입니다.</span></li>
  </ul>
</div>

<?php endif; ?>
