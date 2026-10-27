<div class="card adm-login">
  <h1 class="adm-ttl">직원 로그인</h1>
  <p class="adm-lead">등기 관리자 화면은 직원 계정으로만 들어올 수 있습니다.</p>

  <?php if (!empty($expired)): ?>
  <div class="alert alert--info"><b>안전을 위해 로그아웃했습니다</b>유휴 30분·접속 9시간이 지났거나, 계정 정보가 바뀌었습니다. 다시 로그인해 주십시오.</div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
  <div class="alert alert--err"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/admin/login">
    <?= csrf_field() ?>
    <div class="frm__field">
      <label for="login_id">아이디</label>
      <input type="text" id="login_id" name="login_id" autocomplete="username" required autofocus>
    </div>
    <div class="frm__field">
      <label for="password">비밀번호</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn--fill btn--full">로그인</button>
  </form>

  <div class="alert alert--info" style="margin-top:18px">
    <b>비밀번호 5회 오류 시 15분 잠금 · 2단계 인증(OTP) 필수</b>
    비밀번호 다음에 휴대폰 인증 앱(Google Authenticator 등)의 6자리 코드를 넣습니다.
    계정이 잠기거나 휴대폰을 잃어버리면 admin 권한 직원에게 OTP 초기화를 요청해 주십시오.
  </div>
</div>
