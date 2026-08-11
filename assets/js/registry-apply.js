/* 단체등기 온라인 접수 위저드 — dongtan.html #apply

   4단계: 등기유형 → 단지·세대 정보 → 예상비용 확인 → 접수
   계산은 registry-portal.js 가 노출하는 window.JLRegCalc 를 그대로 쓴다
   (기준 세율·매입률이 data/registry.json 한 곳에서만 관리되도록).

   서버가 없는 동안의 접수 수단은 상담폼과 같은 원칙 —
   정리된 내용을 메일 앱으로 넘기거나 복사한다. 등기포털(2단계)이
   서버와 함께 준비되면 send() 부분만 실제 접수 API 로 바꾼다.
*/
(function () {
  'use strict';

  var root = document.querySelector('[data-apply]');
  if (!root) return;

  var TO = 'jllaw2020@naver.com';

  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };
  var won = function (n) { return Math.round(n).toLocaleString('ko-KR') + '원'; };
  var $ = function (sel) { return root.querySelector(sel); };
  var $$ = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };

  var TYPES = {
    newhome: '입주 아파트 등기',
    conversion: '분양전환 등기',
    landright: '대지권 등기'
  };

  var state = { step: 1, type: '' };

  /* ---------- 단계 전환 ---------- */
  function go(step) {
    state.step = step;
    $$('.apw__panel').forEach(function (p) {
      p.hidden = parseInt(p.dataset.step, 10) !== step;
    });
    $$('.apw__dot').forEach(function (d, i) {
      d.classList.toggle('is-on', i + 1 <= step);
    });
    if (step === 3) renderEstimate();
    if (step === 4) renderSummary();
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ---------- 1단계: 유형 ---------- */
  $$('.apw__type').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.type = btn.dataset.type;
      $$('.apw__type').forEach(function (b) {
        b.classList.toggle('is-on', b === btn);
      });
      go(2);
    });
  });

  /* ---------- 입력 수집 ---------- */
  function val(id) {
    var el = $('#' + id);
    return el ? el.value.trim() : '';
  }

  function inputs() {
    return {
      complex: val('aComplex'),
      unit: val('aUnit'),
      name: val('aName'),
      tel: val('aTel'),
      price: parseFloat(val('aPrice')) || 0,          // 억
      area: parseFloat(val('aArea')) || 0,
      metro: val('aRegion') === 'metro',
      loan: val('aLoan'),
      note: val('aNote')
    };
  }

  function say(msg) {
    var el = $('[data-apply-msg]');
    if (el) el.textContent = msg;
  }

  $('[data-apply-next2]').addEventListener('click', function () {
    var v = inputs();
    if (!v.complex) { say('단지명을 입력해 주세요.'); $('#aComplex').focus(); return; }
    if (!v.name) { say('성함을 입력해 주세요.'); $('#aName').focus(); return; }
    if (!v.tel) { say('연락처를 입력해 주세요.'); $('#aTel').focus(); return; }
    say('');
    go(3);
  });

  /* ---------- 3단계: 예상비용 ---------- */
  function estimate() {
    var C = window.JLRegCalc;
    var v = inputs();
    // 값이 없는 것과 세율표를 아직 못 받은 것은 다른 상황이다. 안내 문구가 달라야 한다
    if (!C) return 'loading';
    if (!v.price) return null;

    var price = v.price * 100000000;
    var std = price * 0.7;                    // 시가표준액 추정 (분양가 70%)
    var aRate = C.acqRate(price);
    var acq = price * aRate / 100;
    var edu = acq * C.cfg.acquisitionTax.eduTaxRatio;
    var rural = (v.area > C.cfg.acquisitionTax.ruralTaxExemptArea)
      ? price * C.cfg.acquisitionTax.ruralTaxRate / 100 : 0;
    var bRate = C.bondRate(std, v.metro);
    var bondBuy = std * bRate / 100;
    var discount = parseFloat(C.cfg.bond.rate) || 0;
    var bondLoss = bondBuy * discount / 100;
    var stamp = C.stampDuty(price, true);      // 입주 아파트 — 주택 기준
    var fee = C.cfg.misc.registrationFee + C.cfg.misc.certFee;
    return {
      total: acq + edu + rural + bondLoss + stamp + fee,
      acq: acq, edu: edu, rural: rural,
      bondLoss: bondLoss, stamp: stamp, fee: fee,
      aRate: aRate, discount: discount, rateDate: C.cfg.bond.rateDate
    };
  }

  function renderEstimate() {
    var out = $('[data-apply-estimate]');
    var v = inputs();
    var e = estimate();
    if (e === 'loading') {
      out.innerHTML = '<p class="apw__note">세율표를 불러오는 중입니다. 잠시만 기다려 주세요.</p>';
      return;
    }
    if (!e) {
      out.innerHTML = '<p class="apw__note">분양가를 입력하지 않아 예상 비용은 접수 후 담당자가 안내해 드립니다.</p>';
      return;
    }
    out.innerHTML =
      '<div class="apw__total"><span>예상 등기비용</span><strong>' + won(e.total) + '</strong></div>' +
      '<ul class="apw__lines">' +
        '<li>취득세 ' + e.aRate + '% ' + won(e.acq) + ' · 지방교육세 ' + won(e.edu) +
          (e.rural ? ' · 농특세 ' + won(e.rural) : '') + '</li>' +
        '<li>국민주택채권 즉시매도 손실 ' + won(e.bondLoss) +
          ' <small>(할인율 ' + e.discount + '% · 기준일 ' + esc(e.rateDate) + ')</small></li>' +
        '<li>인지세 · 수수료 ' + won(e.stamp + e.fee) + '</li>' +
      '</ul>' +
      '<p class="apw__note">시가표준액은 분양가의 70%로 추정한 <b>참고용</b>이며 1주택 기준입니다. ' +
      '법무 수수료는 단지 협약 조건에 따라 별도 안내됩니다.</p>';
  }

  /* ---------- 4단계: 접수 ---------- */
  function body() {
    var v = inputs();
    var e = estimate();
    var lines = [
      '[단체등기 온라인 접수]',
      '등기유형 : ' + (TYPES[state.type] || '미선택'),
      '단지명   : ' + v.complex,
      v.unit ? '동·호수  : ' + v.unit : null,
      '성함     : ' + v.name,
      '연락처   : ' + v.tel,
      v.price ? '분양가   : ' + v.price + '억원' : null,
      v.area ? '전용면적 : ' + v.area + '㎡' : null,
      '대출     : ' + (v.loan === 'loan' ? '실행 예정' : '없음(자납)'),
      e ? '예상비용 : ' + won(e.total) + ' (참고용)' : null,
      v.note ? '' : null,
      v.note ? '[요청사항]\n' + v.note : null,
      '',
      '— 개인정보 수집·이용에 동의함'
    ].filter(function (x) { return x !== null; });
    return lines.join('\n');
  }

  function renderSummary() {
    var v = inputs();
    $('[data-apply-summary]').innerHTML =
      '<table class="calc__table"><tbody>' +
      '<tr><th>등기유형</th><td>' + esc(TYPES[state.type] || '미선택') + '</td></tr>' +
      '<tr><th>단지</th><td>' + esc(v.complex) + (v.unit ? ' ' + esc(v.unit) : '') + '</td></tr>' +
      '<tr><th>신청인</th><td>' + esc(v.name) + ' · ' + esc(v.tel) + '</td></tr>' +
      '</tbody></table>';
  }

  $('[data-apply-send]').addEventListener('click', function () {
    var agree = $('#aAgree');
    if (agree && !agree.checked) {
      say('개인정보 수집·이용에 동의해 주셔야 접수할 수 있습니다.');
      agree.focus();
      return;
    }
    var url = 'mailto:' + TO +
      '?subject=' + encodeURIComponent('[단체등기 접수] ' + inputs().complex + ' · ' + inputs().name) +
      '&body=' + encodeURIComponent(body());
    if (url.length > 1800) {
      say('내용이 길어 "내용 복사"로 보내주세요.');
      return;
    }
    window.location.href = url;
    say('메일 프로그램을 열었습니다. 보내기를 눌러야 접수가 완료됩니다.');
  });

  $('[data-apply-copy]').addEventListener('click', function () {
    var text = body() + '\n\n받는 곳: ' + TO;
    function done(ok) {
      say(ok ? '복사했습니다. ' + TO + ' 또는 카카오톡 채널로 붙여넣어 주세요.'
             : '복사하지 못했습니다. 내용을 직접 복사해 주세요.');
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
    } else { done(false); }
  });

  $$('.apw__back').forEach(function (b) {
    b.addEventListener('click', function () { go(state.step - 1); });
  });
  $('[data-apply-next3]').addEventListener('click', function () { go(4); });

  // 세율표는 따로 받아 온다. 다 받으면 3단계를 다시 그려, 기다리는 사이 떠 있던
  // "불러오는 중" 문구가 실제 금액으로 바뀌게 한다.
  document.addEventListener('jlreg:ready', function () {
    if (state.step === 3) renderEstimate();
  });
})();
