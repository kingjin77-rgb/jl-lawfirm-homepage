<h1 class="adm-ttl">직원 계정 관리</h1>
<p class="adm-lead">admin 권한 전용 화면입니다. 직원마다 계정을 따로 두고 작업기록을 남깁니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>
<?php if (!empty($enrollOut)): ?>
<div class="alert alert--warn" id="enroll-out">
  <b><?= h($enrollOut['login_id']) ?> 등록코드: <code id="enroll-code-value"><?= h($enrollOut['code']) ?></code> (24시간 · 1회용)</b>
  이 화면에서 한 번만 보여드립니다. 메신저·메일 말고 직접(전화·대면) 알려 주십시오. 본인이 첫 로그인 때 이 코드를 넣어야 OTP 를 등록할 수 있습니다.
</div>
<?php endif; ?>

<div class="adm-sec">
  <h2>새 직원 등록</h2>
  <form method="post" action="/admin/config/staff" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="create">
    <div class="adm-grid" style="grid-template-columns: 1fr 1fr 140px 1fr">
      <div class="adm-field"><label>아이디</label><input type="text" name="login_id" required pattern="[A-Za-z0-9_]{3,50}"></div>
      <div class="adm-field"><label>이름</label><input type="text" name="name" required></div>
      <div class="adm-field"><label>권한</label>
        <select name="role"><option value="staff">staff (일반)</option><option value="admin">admin (관리자)</option></select>
      </div>
      <div class="adm-field"><label>비밀번호 (10자 이상)</label><input type="password" name="password" required minlength="10" autocomplete="new-password"></div>
    </div>
    <p style="margin:14px 0 0"><button type="submit" class="btn btn--fill btn--sm">등록</button></p>
  </form>
</div>

<div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>번호</th><th>아이디</th><th>이름</th><th class="ctr">권한</th><th class="ctr">상태</th><th class="ctr">OTP</th><th>등록일</th><th class="ctr">관리</th></tr></thead>
    <tbody>
    <?php foreach ($list as $s): ?>
    <tr>
      <td class="num"><?= (int)$s['id'] ?></td>
      <td class="nowrap"><?= h($s['login_id']) ?></td>
      <td class="nowrap"><?= h($s['name']) ?></td>
      <td class="ctr"><?= h($s['role']) ?></td>
      <td class="ctr">
        <?php if (!$s['is_active']): ?><span class="mark-wait">중지</span>
        <?php elseif ($s['locked_until'] !== null && strtotime($s['locked_until']) > time()): ?><span class="neg">잠김</span>
        <?php else: ?><span class="mark-done">사용</span><?php endif; ?>
      </td>
      <td class="ctr nowrap"><?= (int)$s['otp_on'] === 1 ? '<span class="mark-done">사용</span> <span>복구 ' . (int)$s['rc_left'] . '</span>' : '<span class="mark-wait">미등록</span>' ?></td>
      <td class="nowrap"><?= h(substr((string)$s['created_at'], 0, 10)) ?></td>
      <td class="ctr">
        <form method="post" action="/admin/config/staff" style="display:flex; gap:6px; justify-content:center; flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input type="password" name="password" placeholder="새 비밀번호" minlength="10" autocomplete="new-password"
                 style="font:inherit; font-size:14px; border:1px solid var(--line); border-radius:6px; padding:4px 8px; width:130px">
          <button type="submit" name="act" value="passwd" class="btn btn--xs">재설정</button>
          <?php if ((int)$s['otp_on'] !== 1): ?>
          <button type="submit" name="act" value="enroll_issue" class="btn btn--xs">등록코드 발급</button>
          <?php endif; ?>
          <button type="submit" name="act" value="otp_reset" class="btn btn--xs"
                  data-confirm="<?= h($s['login_id']) ?> 의 OTP 와 복구코드를 초기화합니다. 다음 로그인 때 새로 등록해야 합니다. 계속할까요?">OTP 초기화</button>
          <?php if ((int)$s['id'] !== (int)$staff['id']): ?>
          <button type="submit" name="act" value="toggle" class="btn btn--xs<?= $s['is_active'] ? ' btn--danger' : '' ?>"
                  data-confirm="<?= $s['is_active'] ? '이 계정을 중지할까요?' : '이 계정을 다시 사용할까요?' ?>">
            <?= $s['is_active'] ? '중지' : '재사용' ?>
          </button>
          <?php endif; ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
