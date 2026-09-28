<div class="card adm-login">
  <h1 class="adm-ttl">직원 로그인</h1>
  <p class="adm-lead">등기 관리자 화면은 직원 계정으로만 들어올 수 있습니다.</p>

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
    <b>비밀번호 5회 오류 시 15분 잠금</b>
    계정이 잠기면 15분 뒤 다시 시도하거나 admin 권한 직원에게 문의해 주십시오.
  </div>
</div>
