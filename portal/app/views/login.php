<?php /* 로그인 화면 — tracking.html 의 조회 폼과 같은 결 */ ?>
<div class="card card--login">
  <h1 class="ttl">등기 진행 조회</h1>
  <p class="lead">
    <span class="s">맡기신 세대의 등기가 어디까지 진행됐는지 알려드립니다.</span>
    <span class="s">본인 확인을 위해 아래 내용을 채워 주십시오.</span>
  </p>

  <?php if ($lockout): ?>
  <div class="alert alert--warn" role="alert">
    <b>조회가 잠시 잠겼습니다.</b>
    <span class="s">틀린 입력이 여러 번 이어져 이 세대의 조회를 1시간 동안 멈췄습니다.</span>
    <span class="s">급하시면 <a href="tel:18994252">문의 1899-4252</a> 로 전화해 주십시오.</span>
  </div>
  <?php elseif ($error !== ''): ?>
  <p class="alert alert--err" role="alert"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" action="/login" class="frm">
    <?= csrf_field() ?>
    <div class="frm__field">
      <label for="inComplex">아파트</label>
      <select id="inComplex" name="complex_id" required>
        <option value="">단지를 선택해 주십시오</option>
        <?php foreach ($complexes as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= $old['complex_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="frm__grid">
      <div class="frm__field">
        <label for="inDong">동</label>
        <input type="text" id="inDong" name="dong" inputmode="numeric" autocomplete="off"
               placeholder="101" maxlength="5" value="<?= h($old['dong']) ?>" required>
      </div>
      <div class="frm__field">
        <label for="inHo">호</label>
        <input type="text" id="inHo" name="ho" inputmode="numeric" autocomplete="off"
               placeholder="1203" maxlength="6" value="<?= h($old['ho']) ?>" required>
      </div>
    </div>
    <div class="frm__grid">
      <div class="frm__field">
        <label for="inName">이름</label>
        <input type="text" id="inName" name="name" autocomplete="name"
               placeholder="홍길동" value="<?= h($old['name']) ?>" required>
      </div>
      <div class="frm__field">
        <label for="inBirth">생년월일</label>
        <input type="text" id="inBirth" name="birth6" inputmode="numeric" autocomplete="off"
               placeholder="예: 900305" maxlength="6" required>
      </div>
    </div>
    <button type="submit" class="btn btn--fill btn--full">조회하기</button>
  </form>

  <section class="help">
    <h2>조회가 안 되시나요?</h2>
    <ul>
      <li>
        <b>계약자 성함으로 넣으셨습니까</b>
        <span class="s">배우자나 부모님 명의로 계약하신 경우가 많습니다.</span>
        <span class="s">계약서에 적힌 분의 성함과 생년월일로 조회해 주십시오.</span>
      </li>
      <li>
        <b>동과 호는 숫자만 넣습니다</b>
        <span class="s">101동 1203호라면 101 과 1203 을 넣습니다.</span>
      </li>
      <li>
        <b>서류를 보내시기 전이면 아직 조회되지 않습니다</b>
        <span class="s">등기 서류가 저희에게 들어온 뒤부터 진행 단계가 나옵니다.</span>
      </li>
    </ul>
    <a class="btn btn--fill" href="tel:18994252">문의 1899-4252</a>
  </section>
</div>
