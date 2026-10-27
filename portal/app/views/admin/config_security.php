<h1 class="adm-ttl">보안 설정</h1>
<p class="adm-lead">admin 권한 전용입니다. 관리자 화면에 들어올 수 있는 접속 위치를 정하고, 자동 차단된 IP 를 확인합니다.</p>

<?php if ($notice !== ''): ?><div class="alert alert--ok" id="sec-notice"><?= h($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert--err" id="sec-error"><?= h($error) ?></div><?php endif; ?>

<div class="adm-sec">
  <h2>① 관리자 IP 허용목록</h2>
  <div class="alert alert--info" style="margin:0 0 14px">
    <b>지금 접속한 IP: <span id="my-ip"><?= h($myIp) ?></span></b>
    한 줄에 하나씩 IP(예: 211.47.74.37) 또는 대역(예: 211.47.74.0/24)을 넣습니다. # 뒤는 메모입니다.
    비워 두면 제한하지 않습니다. 사무실 고정 IP 와 대표님 자택 IP 를 함께 넣어 두십시오.
  </div>
  <form method="post" action="/admin/config/security">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="allowlist">
    <div class="adm-field">
      <label for="allowlist">허용 IP / CIDR</label>
      <textarea id="allowlist" name="allowlist" rows="6" placeholder="211.47.74.37   # 본사 사무실"><?= h($text) ?></textarea>
    </div>
    <p style="margin:14px 0 0"><button type="submit" class="btn btn--fill btn--sm">저장</button></p>
  </form>
</div>

<div class="adm-sec">
  <h2>② 자동 차단 중인 IP (10분 내 5개 대상 또는 30회 실패 → 1시간)</h2>
  <?php if ($blocks === []): ?>
  <div class="alert alert--ok" style="margin:0"><b>차단 중인 IP 없음</b>최근 1시간 안에 자동 차단된 접속 위치가 없습니다.</div>
  <?php else: ?>
  <div class="tbl-scroll" style="border:0; margin:0">
    <table class="tbl" id="ipblock-table">
      <thead><tr><th>IP</th><th>사유</th><th>차단 시각</th><th>해제 예정</th><th class="ctr">관리</th></tr></thead>
      <tbody>
      <?php foreach ($blocks as $b): ?>
      <tr>
        <td class="nowrap"><?= h($b['ip']) ?></td>
        <td><?= h($b['reason']) ?></td>
        <td class="nowrap"><?= h($b['created_at']) ?></td>
        <td class="nowrap"><?= h($b['blocked_until']) ?></td>
        <td class="ctr">
          <form method="post" action="/admin/config/security" data-confirm="<?= h($b['ip']) ?> 의 차단을 풀까요?">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="release">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="btn btn--xs">해제</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
