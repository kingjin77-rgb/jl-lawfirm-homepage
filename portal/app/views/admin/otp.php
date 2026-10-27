<?php /* 직원 OTP 입력 — 로그인 2단계(mode=login) · 민감 작업 재확인(mode=reauth) */ ?>
<div class="card adm-login">
  <h1 class="adm-ttl"><?= $mode === 'login' ? '2단계 인증' : 'OTP 재확인' ?></h1>
  <p class="adm-lead">
    <?php if ($mode === 'login'): ?>
    <?= h($who['name']) ?> 님, 인증 앱에 나온 6자리 코드를 넣어 주십시오.
    <?php else: ?>
    개인정보를 내보내거나 열람하는 작업입니다. 인증 앱의 새 6자리 코드를 넣어 주십시오 (10분간 유지).
    <?php endif; ?>
  </p>

  <?php if ($error !== ''): ?><div class="alert alert--err" id="otp-error"><?= h($error) ?></div><?php endif; ?>

  <form method="post" action="<?= h($action) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <div class="frm__field">
      <label for="otp-code"><?= $mode === 'login' ? 'OTP 6자리 또는 복구코드' : 'OTP 6자리' ?></label>
      <input type="text" id="otp-code" name="code" required autofocus autocomplete="one-time-code"
             <?= $mode === 'login' ? 'maxlength="11"' : 'inputmode="numeric" maxlength="6" pattern="[0-9]{6}"' ?>>
    </div>
    <button type="submit" class="btn btn--fill btn--full">확인</button>
  </form>

  <div class="alert alert--info" style="margin-top:18px">
    <b>같은 코드는 한 번만 쓸 수 있습니다</b>
    <?php if ($mode === 'login'): ?>
    휴대폰을 잃어버렸으면 등록 때 받은 복구코드(예: ABCDE-FGHJK)를 넣습니다. 복구코드도 한 번씩만 됩니다.
    <?php else: ?>
    방금 로그인에 쓴 코드라면 앱에 다음 코드가 나올 때까지(30초 이내) 기다렸다 넣어 주십시오.
    <?php endif; ?>
  </div>
  <?php if ($mode === 'login'): ?>
  <p style="margin:14px 0 0"><a class="btn btn--sm" href="/admin/logout">처음으로</a></p>
  <?php else: ?>
  <p style="margin:14px 0 0"><a class="btn btn--sm" href="/admin">취소</a></p>
  <?php endif; ?>
</div>
