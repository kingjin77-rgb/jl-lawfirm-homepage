/* 개별등기 온라인 접수 위저드 — dongtan.html #apply

   5단계: 등기유형 → 단지·세대 정보 → 준비서류 → 예상비용 → 접수

   준비서류는 현장 업무 흐름을 그대로 옮겼다. 지금까지는 직원이 세대마다
   전입 여부·대출 여부·명의 형태를 보고 판단해 문자로 보완 요청을 보냈다.
   같은 판단을 접수 시점에 화면이 대신하면, 처음부터 맞는 서류가 들어온다.
   갈래는 data/registry.json 의 docs 에 있어 관리자 페이지에서 고칠 수 있다.
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
    if (step === 3) renderDocs();
    if (step === 4) renderEstimate();
    if (step === 5) renderSummary();
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
      moveIn: val('aMoveIn'),
      owner: val('aOwner'),
      pay: val('aPay'),
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

  /* ---------- 3단계: 준비서류 ----------
     조건에 맞는 갈래만 모아 한 장으로 만든다. 세대마다 다른 것은
     전입 여부·대출 여부·명의 형태 세 가지다. */
  function docList() {
    var C = window.JLRegCalc;
    if (!C || !C.cfg.docs) return null;
    var D = C.cfg.docs;
    var v = inputs();
    var groups = [];

    function add(title, conf, note) {
      if (!conf) return;
      var items = (conf.items || []).filter(function (it) {
        // 대출 세대에만 해당하는 항목은 자납 세대에서 뺀다
        return !(it.onlyLoan && v.loan !== 'loan');
      });
      if (!items.length) return;
      groups.push({ title: title, note: note || conf.note || '', items: items });
    }

    add('모든 세대 공통', { items: D.common });
    add(TYPES[state.type] || '등기 유형', D.byType && D.byType[state.type]);
    add('대출 관련', D.byLoan && D.byLoan[v.loan]);
    add('명의', D.byOwner && D.byOwner[v.owner]);
    add('전입신고', D.byMoveIn && D.byMoveIn[v.moveIn]);

    return { groups: groups, submit: D.submit, note: D._note };
  }

  function renderDocs() {
    var out = $('[data-apply-docs]');
    var d = docList();
    if (!d) {
      out.innerHTML = '<p class="apw__note">서류 목록을 불러오는 중입니다. 잠시만 기다려 주세요.</p>';
      return;
    }
    var v = inputs();
    var count = d.groups.reduce(function (n, g) { return n + g.items.length; }, 0);

    var html =
      '<div class="dcs">' +
        '<div class="dcs__head">' +
          '<strong>' + esc(v.complex || '단지') + (v.unit ? ' ' + esc(v.unit) : '') + '</strong>' +
          '<span>모두 ' + count + '가지</span>' +
        '</div>' +
        d.groups.map(function (g) {
          return '<div class="dcs__grp">' +
            '<h4>' + esc(g.title) + '</h4>' +
            (g.note ? '<p class="dcs__note">' + esc(g.note) + '</p>' : '') +
            '<ul>' + g.items.map(function (it) {
              return '<li><b>' + esc(it.name) + '</b>' +
                     (it.detail ? '<span>' + esc(it.detail) + '</span>' : '') + '</li>';
            }).join('') + '</ul>' +
          '</div>';
        }).join('');

    var s = d.submit;
    if (s) {
      html +=
        '<div class="dcs__how">' +
          '<h4>보내는 방법</h4>' +
          '<p class="dcs__warn">' + esc(s.deadlineNote || '') + '</p>' +
          '<ul>' +
            '<li><b>' + esc(s.original.label) + '</b><span>' + esc(s.original.address) +
              ' — ' + esc(s.original.note) + '</span></li>' +
            '<li><b>' + esc(s.copy.label) + '</b><span>팩스 ' + esc(s.copy.fax) +
              ' · 메일 ' + esc(s.copy.mail) + '</span></li>' +
          '</ul>' +
          '<p class="dcs__note">' + esc(s.stampPaper || '') + '</p>' +
          '<p class="dcs__note">' + esc(s.lead || '') + '</p>' +
        '</div>';
    }
    if (d.note) html += '<p class="dcs__note">' + esc(d.note) + '</p>';
    html += '</div>';
    out.innerHTML = html;
  }

  var printBtn = $('[data-docs-print]');
  if (printBtn) printBtn.addEventListener('click', function () { window.print(); });

  /* ---------- 4단계: 예상비용 ---------- */
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
      '[개별등기 온라인 접수]',
      '등기유형 : ' + (TYPES[state.type] || '미선택'),
      '단지명   : ' + v.complex,
      v.unit ? '동·호수  : ' + v.unit : null,
      '성함     : ' + v.name,
      '연락처   : ' + v.tel,
      v.price ? '분양가   : ' + v.price + '억원' : null,
      v.area ? '전용면적 : ' + v.area + '㎡' : null,
      '지역     : ' + (v.metro ? '서울·광역시' : '그 밖의 지역'),
      '대출     : ' + (v.loan === 'loan' ? '실행 예정' : '없음(자납)'),
      '전입신고 : ' + (v.moveIn === 'yes' ? '완료 — 보존등기 후 초본·인감 추가 제출 대상' : '전'),
      '명의     : ' + (v.owner === 'joint' ? '공동명의 — 명의자별 서류 각각' : '단독명의'),
      '취득세   : ' + (v.pay === 'self' ? '직접 납부 희망 (위택스·은행)' : '법무법인 대납'),
      e ? '예상비용 : ' + won(e.total) + ' (참고용)' : null,
      v.note ? '' : null,
      v.note ? '[요청사항]\n' + v.note : null,
      '',
      // 대장에 그대로 옮겨 붙일 수 있게 탭으로 구분한 줄을 함께 보낸다.
      // 직원이 메일을 보고 다시 타이핑하는 일을 없애려는 것이다.
      '[대장 붙여넣기용 — 탭 구분]',
      [v.complex, v.unit, v.name, v.tel,
       TYPES[state.type] || '',
       v.price ? v.price + '억' : '',
       v.area ? v.area : '',
       v.metro ? '서울·광역시' : '그 밖의 지역',
       v.loan === 'loan' ? '대출' : '자납',
       v.moveIn === 'yes' ? '전입완료' : '전입전',
       v.owner === 'joint' ? '공동명의' : '단독명의',
       v.pay === 'self' ? '직접납부' : '대납'
      ].join('\t'),
      '',
      '— 개인정보 수집·이용에 동의함'
    ].filter(function (x) { return x !== null; });
    return lines.join('\n');
  }

  function renderSummary() {
    var v = inputs();
    var d = docList();
    var count = d ? d.groups.reduce(function (n, g) { return n + g.items.length; }, 0) : 0;
    $('[data-apply-summary]').innerHTML =
      '<table class="calc__table"><tbody>' +
      '<tr><th>등기유형</th><td>' + esc(TYPES[state.type] || '미선택') + '</td></tr>' +
      '<tr><th>단지</th><td>' + esc(v.complex) + (v.unit ? ' ' + esc(v.unit) : '') + '</td></tr>' +
      '<tr><th>신청인</th><td>' + esc(v.name) + ' · ' + esc(v.tel) + '</td></tr>' +
      '<tr><th>대출 · 전입</th><td>' +
        (v.loan === 'loan' ? '대출 실행' : '자납') + ' · ' +
        (v.moveIn === 'yes' ? '전입 완료' : '전입 전') + ' · ' +
        (v.owner === 'joint' ? '공동명의' : '단독명의') + '</td></tr>' +
      '<tr><th>취득세</th><td>' + (v.pay === 'self' ? '직접 납부' : '법무법인 대납') + '</td></tr>' +
      (count ? '<tr><th>준비서류</th><td>' + count + '가지 — 앞 단계에서 확인하실 수 있습니다</td></tr>' : '') +
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
  $('[data-apply-next4]').addEventListener('click', function () { go(5); });

  // 세율표는 따로 받아 온다. 다 받으면 3단계를 다시 그려, 기다리는 사이 떠 있던
  // "불러오는 중" 문구가 실제 금액으로 바뀌게 한다.
  document.addEventListener('jlreg:ready', function () {
    if (state.step === 3) renderDocs();
    if (state.step === 4) renderEstimate();
  });
})();
