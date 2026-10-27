<?php /* 복구코드 — 새로 발급된 경우에만 원문 표시 */ ?>
<h1 class="adm-ttl">복구코드</h1>
<p class="adm-lead">휴대폰을 잃어버렸을 때 OTP 대신 한 번씩 쓰는 코드입니다. 남은 코드 <b id="recovery-left"><?= (int)$left ?></b>개.</p>

<?php if (is_array($codes) && $codes !== []): ?>
<div class="adm-sec">
  <h2>지금 한 번만 보여드립니다 — 종이에 적어 안전한 곳에 두십시오</h2>
  <ul class="adm-recovery" id="recovery-codes">
    <?php foreach ($codes as $c): ?><li><code><?= h($c) ?></code></li><?php endforeach; ?>
  </ul>
  <div class="alert alert--warn" style="margin:14px 0 0">
    <b>이 화면을 벗어나면 다시 볼 수 없습니다</b>
    PC 파일·메신저·사진으로 남기지 마십시오. 다 쓰거나 잃어버리면 아래에서 새로 발급합니다(기존 코드는 폐기).
  </div>
</div>
<?php endif; ?>

<div class="adm-sec">
  <h2>새로 발급</h2>
  <form method="post" action="/admin/otp/recovery" data-confirm="기존 복구코드는 전부 폐기되고 새 코드 8개가 나옵니다. 계속할까요?">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn--sm">복구코드 새로 발급 (OTP 재확인)</button>
  </form>
</div>
<p><a class="btn btn--fill" href="/admin">대시보드로</a></p>
