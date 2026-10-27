<?php
/**
 * 문자 인증번호 입력 — 로그인 2단계(/login/verify)와 재인증(/verify) 공용.
 * $mode: 'login' | 'reauth', $notice: customer_send_code() 결과, $error, $action, $next
 */
$tail = (string)($notice['tail'] ?? '');
$sent = !empty($notice['ok']);
?>
<div class="card card--login">
  <h1 class="ttl"><?= $mode === 'login' ? '본인 확인 문자' : '한 번 더 본인 확인' ?></h1>
  <p class="lead">
    <?php if ($mode === 'login'): ?>
    <span class="s">계약하신 분의 휴대폰으로 인증번호 6자리를 보냈습니다.</span>
    <span class="s">문자를 받으신 뒤 3분 안에 넣어 주십시오.</span>
    <?php else: ?>
    <span class="s">계좌·주소를 등록하거나 바꾸실 때는 본인 확인을 한 번 더 합니다.</span>
    <span class="s">인증번호를 받아 3분 안에 넣어 주십시오.</span>
    <?php endif; ?>
  </p>

  <?php if ($tail !== '' && ($sent || $mode === 'login')): ?>
  <p class="otp-sent">받는 번호 <b>010-****-<?= h($tail) ?></b></p>
  <?php endif; ?>

  <?php if (!empty($notice['dev_code'])): ?>
  <div class="otp-dev" id="dev-code" role="status">
    <b>개발 모드 — 실제 문자는 나가지 않았습니다</b>
    <span class="s">인증번호 <strong data-dev-code><?= h($notice['dev_code']) ?></strong></span>
  </div>
  <?php endif; ?>

  <?php if (!empty($notice['error']) && $notice['error'] !== 'nophone'): ?>
  <p class="alert alert--warn" role="alert"><?= h($notice['error']) ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
  <p class="alert alert--err" role="alert" id="otp-error"><?= h($error) ?></p>
  <?php endif; ?>

  <?php if ($mode === 'reauth' && !$sent && empty($notice)): ?>
  <form method="post" action="<?= h($action) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="send">
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <button type="submit" class="btn btn--fill btn--full">인증번호 받기</button>
  </form>
  <?php else: ?>
  <form method="post" action="<?= h($action) ?>" class="frm" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="verify">
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <div class="frm__field">
      <label for="inCode">인증번호 6자리</label>
      <input type="text" id="inCode" name="code" class="otp-in" inputmode="numeric" pattern="[0-9]{6}"
             maxlength="6" autocomplete="one-time-code" required autofocus>
    </div>
    <button type="submit" class="btn btn--fill btn--full">확인</button>
  </form>
  <div class="otp-actions">
    <form method="post" action="<?= h($action) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="send">
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <button type="submit" class="btn" id="otp-resend">인증번호 다시 받기</button>
    </form>
    <?php if ($mode === 'login'): ?>
    <a class="btn" href="/login?reset=1">처음으로</a>
    <?php else: ?>
    <a class="btn" href="/home">취소</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="alert alert--info" style="margin:22px 0 0">
    <b>문자가 오지 않으면</b>
    <span class="s">다시 받기는 1분 뒤부터, 1시간에 5번까지 됩니다.</span>
    <span class="s">번호가 바뀌셨으면 <a href="tel:18994252">등기센터 1899-4252</a> 로 연락해 주십시오.</span>
  </div>
</div>
