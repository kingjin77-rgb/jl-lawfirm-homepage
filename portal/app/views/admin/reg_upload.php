<h1 class="adm-ttl">등기진행 등록 (엑셀 업로드)</h1>
<p class="adm-lead">직원이 쓰던 등기진행 엑셀을 그대로 올립니다. 미리보기로 확인한 뒤 확정해야 반영됩니다.</p>

<?php if ($error !== ''): ?><div class="alert alert--err"><?= h($error) ?></div><?php endif; ?>

<?php if ($result !== null): ?>
<div class="alert alert--ok">
  <b>반입 완료</b>
  <?= number_format($result['total']) ?>행 처리 — 신규 세대 <?= number_format($result['created']) ?> ·
  기존 세대 갱신 <?= number_format($result['updated']) ?>.
</div>
<p style="margin:0 0 24px; display:flex; gap:10px">
  <a class="btn btn--fill btn--sm" href="/admin/registration">등기진행 현황 보기</a>
  <a class="btn btn--sm" href="/admin/registration/upload">다른 파일 올리기</a>
</p>
<?php endif; ?>

<?php if ($preview !== null): ?>
<div class="adm-sec">
  <h2>미리보기 — <?= h($preview['complex_name']) ?> · <?= h($preview['file_name']) ?></h2>
  <p style="margin:0 0 14px; font-size:15px">
    자료 <b><?= number_format(count($preview['rows'])) ?>행</b>
    <?php if ($preview['errors'] !== []): ?>
    · <span class="neg">오류 <?= number_format(count($preview['errors'])) ?>행</span>
    <?php else: ?>
    · 오류 없음
    <?php endif; ?>
    — 앞 5행을 보여드립니다.
  </p>

  <div class="tbl-scroll">
    <table class="tbl">
      <thead><tr>
        <th>엑셀행</th><th class="ctr">동</th><th class="ctr">호</th><th>명의인 (생년월일 / 핸드폰)</th>
        <th>완료 단계</th><th class="num">취득세</th><th class="num">합계</th><th class="ctr">입금일</th><th class="num">입금액</th><th class="num">차액</th><th>주소·은행</th>
      </tr></thead>
      <tbody>
      <?php foreach (array_slice($preview['rows'], 0, 5) as $r): ?>
      <tr>
        <td class="num"><?= (int)$r['excel_row'] ?></td>
        <td class="ctr"><?= h($r['dong']) ?></td>
        <td class="ctr"><?= h($r['ho']) ?></td>
        <td>
          <?php foreach ($r['owners'] as $o): ?>
          <span class="nowrap" style="display:block"><?= h($o['name']) ?> (<?= h($o['birth6']) ?><?= $o['phone'] !== '' ? ' / ' . h($o['phone']) : '' ?>)</span>
          <?php endforeach; ?>
        </td>
        <td class="nowrap">
          <?php $done = [];
          foreach ($r['steps'] as $n => $s) { if ($s['done']) { $done[] = $n; } }
          echo $done === [] ? '<span class="mark-wait">—</span>' : h(implode('·', $done)) . ' 완료'; ?>
        </td>
        <td class="num"><?= number_format($r['cost']['acq_tax']) ?></td>
        <td class="num"><b><?= number_format($r['total']) ?></b></td>
        <td class="ctr nowrap"><?= h((string)$r['paid_date']) ?: '—' ?></td>
        <td class="num"><?= number_format($r['paid']) ?></td>
        <td class="num<?= $r['diff'] < 0 ? ' neg' : '' ?>"><?= number_format($r['diff']) ?></td>
        <td style="max-width:260px">
          <?= $r['addr'] !== '' ? h(mask_address($r['addr'])) : '' ?>
          <?= $r['bank'] !== '' ? '<span class="nowrap" style="display:block">' . h($r['bank']) . ' (계좌 반입)</span>' : '' ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($preview['errors'] !== []): ?>
  <div class="alert alert--err">
    <b>오류 행 — 전부 고쳐야 반입됩니다 (부분 반입 없음)</b>
    <ul style="margin:8px 0 0; padding-left:20px">
      <?php foreach ($preview['errors'] as $e): ?>
      <li>엑셀 <?= (int)$e['excel_row'] ?>행 (<?= h($e['dong']) ?>동 <?= h($e['ho']) ?>호): <?= h(implode(' · ', $e['messages'])) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php else: ?>
  <form method="post" action="/admin/registration/upload"
        onsubmit="return confirm('<?= h($preview['complex_name']) ?> 에 <?= number_format(count($preview['rows'])) ?>행을 반입합니다. 같은 동·호 세대는 엑셀 내용으로 갱신됩니다. 계속할까요?')">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="confirm">
    <input type="hidden" name="token" value="<?= h($preview['token']) ?>">
    <p style="display:flex; gap:10px">
      <button type="submit" class="btn btn--fill">이대로 반입 확정</button>
      <a class="btn" href="/admin/registration/upload">취소 (다시 올리기)</a>
    </p>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($preview === null && $result === null): ?>
<div class="adm-sec">
  <h2>1단계 — 아파트 선택 + 엑셀 업로드</h2>
  <form method="post" action="/admin/registration/upload" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="parse">
    <div class="adm-grid" style="grid-template-columns: 1fr 1.6fr">
      <div class="adm-field">
        <label for="up-complex">아파트</label>
        <select id="up-complex" name="complex_id" required>
          <option value="">선택</option>
          <?php foreach ($complexes as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adm-field">
        <label for="up-file">엑셀 파일 (.xlsx)</label>
        <input type="file" id="up-file" name="xlsx" accept=".xlsx" required>
      </div>
    </div>
    <p style="margin:14px 0 0"><button type="submit" class="btn btn--fill">올려서 미리보기</button></p>
  </form>
</div>

<div class="adm-sec">
  <h2>엑셀 양식 (기존 파일 그대로)</h2>
  <ul style="margin:0; padding-left:20px; font-size:15px; line-height:2">
    <li>머리글에 「순번」이 있는 행을 자동으로 찾고, 그 다음 행부터 읽습니다.</li>
    <li>열 순서: 순번 · 동 · 호 · 성명(공동명의는 옆 칸) · 주민번호 앞 6자리(옆 칸) · 진행 8단계(「완료」 표기) ·
        비용 11항목 · 등기비용합계 · 입금일 · 입금액 · 차액 · 미비서류 · 발송일 · 권리증발송주소 · 은행 · 계좌 · 핸드폰(옆 칸)</li>
    <li>같은 동·호가 이미 있으면 그 세대의 명의인·진행·비용을 엑셀 내용으로 갱신합니다.</li>
    <li>주소·계좌는 암호화해 저장합니다. 발송일 열은 아직 저장처가 없어 반입하지 않습니다.</li>
  </ul>
</div>
<?php endif; ?>
