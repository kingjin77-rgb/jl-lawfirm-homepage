<?php /* 직원 OTP 첫 등록 — QR(서버 SVG) + 비밀키 + otpauth URI + 확인 코드 */ ?>
<div class="card adm-otp-setup">
  <h1 class="adm-ttl">2단계 인증(OTP) 등록</h1>
  <p class="adm-lead"><?= h($who['name']) ?> 님, 관리자 화면은 비밀번호와 휴대폰 인증 앱 코드를 함께 씁니다. 처음 한 번만 등록합니다.</p>

  <?php if ($error !== ''): ?><div class="alert alert--err" id="otp-error"><?= h($error) ?></div><?php endif; ?>

  <?php if ($state === 'nocode' || $state === 'locked'): ?>
  <div class="alert alert--warn" id="enroll-needed">
    <b><?= $state === 'locked' ? '잠시 잠겼습니다' : '등록코드가 필요합니다' ?></b>
    <?= $state === 'locked'
        ? '코드를 여러 번 틀려 15분 동안 등록·인증을 멈췄습니다. 잠시 뒤 다시 시도해 주십시오.'
        : 'OTP 등록은 admin 직원이 발급한 등록코드(24시간 유효)가 있어야 시작됩니다. admin 직원에게 「직원 계정 관리 → 등록코드 발급」을 요청해 주십시오.' ?>
  </div>
  <?php elseif ($state === 'enroll'): ?>
  <form method="post" action="/admin/otp/setup" autocomplete="off" class="adm-otp-form" id="enroll-form">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="enroll">
    <div class="frm__field">
      <label for="enroll-code">admin 이 알려 준 등록코드 (예: ABCD-EFGH)</label>
      <input type="text" id="enroll-code" name="enroll_code" maxlength="9" required autofocus>
    </div>
    <button type="submit" class="btn btn--fill btn--full">등록 시작</button>
  </form>
  <?php else: ?>
  <ol class="adm-otp-steps">
    <li>
      <b>휴대폰에 인증 앱을 설치합니다</b>
      Google Authenticator · Microsoft Authenticator · 네이버 앱 OTP 등 무료 앱 어느 것이든 됩니다.
    </li>
    <li>
      <b>앱에서 QR 코드를 찍습니다</b>
      <div class="adm-otp-qr"><?= $qrSvg /* 서버에서 만든 SVG — 사용자 입력 없음 */ ?></div>
      QR 을 못 찍으면 앱의 「설정 키 입력」에 아래 비밀키를 넣습니다 (시간 기준).
      <code class="adm-otp-secret" id="otp-secret"><?= h($secret) ?></code>
      <details class="adm-otp-uri"><summary>otpauth 주소 보기</summary><code id="otp-uri"><?= h($uri) ?></code></details>
    </li>
    <li>
      <b>앱에 나온 6자리를 넣어 확인합니다</b>
      <form method="post" action="/admin/otp/setup" autocomplete="off" class="adm-otp-form">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="confirm">
        <div class="frm__field">
          <label for="otp-code">인증 앱 코드 6자리</label>
          <input type="text" id="otp-code" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus>
        </div>
        <button type="submit" class="btn btn--fill btn--full">확인하고 등록</button>
      </form>
    </li>
  </ol>
  <?php endif; ?>

  <div class="alert alert--warn" style="margin-top:18px">
    <b>비밀키는 다른 사람에게 보여주지 마십시오</b>
    이 화면을 캡처해 메신저로 보내거나 PC 에 저장하지 마십시오. 등록이 끝나면 복구코드 8개가 한 번 나옵니다.
  </div>
  <p style="margin:14px 0 0"><a class="btn btn--sm" href="/admin/logout">취소하고 처음으로</a></p>
</div>
