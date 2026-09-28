<h1 class="adm-ttl">직원 계정 관리</h1>
<p class="adm-lead">admin 권한 전용 화면입니다. 직원마다 계정을 따로 두고 작업기록을 남깁니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

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
    <thead><tr><th>번호</th><th>아이디</th><th>이름</th><th class="ctr">권한</th><th class="ctr">상태</th><th>등록일</th><th class="ctr">관리</th></tr></thead>
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
      <td class="nowrap"><?= h(substr((string)$s['created_at'], 0, 10)) ?></td>
      <td class="ctr">
        <form method="post" action="/admin/config/staff" style="display:flex; gap:6px; justify-content:center; flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input type="password" name="password" placeholder="새 비밀번호" minlength="10" autocomplete="new-password"
                 style="font:inherit; font-size:14px; border:1px solid var(--line); border-radius:6px; padding:4px 8px; width:130px">
          <button type="submit" name="act" value="passwd" class="btn btn--xs">재설정</button>
          <?php if ((int)$s['id'] !== (int)$staff['id']): ?>
          <button type="submit" name="act" value="toggle" class="btn btn--xs<?= $s['is_active'] ? ' btn--danger' : '' ?>"
                  onclick="return confirm('<?= $s['is_active'] ? '이 계정을 중지할까요?' : '이 계정을 다시 사용할까요?' ?>')">
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
