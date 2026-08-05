/* 단체등기센터 포털 — data/registry.json
   1) 등기비용 계산기 (취득세 · 국민주택채권 · 부대비용)
   2) 준비서류 체크리스트 (신청 유형 · 대출 · 등기 종류에 따라 목록이 바뀜)
   3) 진행 타임라인
   4) 진행 단지 목록

   ※ 계산 결과는 참고용 추정치다. 실제 납부액은 시가표준액, 채권 할인율,
      다주택 여부 등에 따라 달라진다.
*/
(function () {
  'use strict';

  // 포털 구성요소는 여러 섹션에 흩어져 있으므로 document 기준으로 찾는다
  if (!document.querySelector('[data-portal]')) return;

  var CFG = null;

  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };
  var won = function (n) { return Math.round(n).toLocaleString('ko-KR') + '원'; };
  var eok = function (n) { return (n / 100000000).toFixed(2) + '억'; };

  /* =======================================================
     1) 등기비용 계산기
     ======================================================= */

  // 주택 유상취득 취득세율(%) — 6~9억 구간은 누진식
  function acqRate(price) {
    var b = CFG.acquisitionTax;
    if (price <= 600000000) return b.brackets[0].rate;
    if (price <= 900000000) {
      // 세율 = 취득가액(억) × 2/3 − 3
      var r = (price / 100000000) * (2 / 3) - 3;
      return Math.round(r * 100) / 100;
    }
    return b.brackets[2].rate;
  }

  function bondRate(standardPrice, isMetro) {
    var b = CFG.bond;
    if (standardPrice < b.exemptUnder) return 0;
    for (var i = 0; i < b.brackets.length; i++) {
      var br = b.brackets[i];
      if (br.upto === null || standardPrice <= br.upto) {
        return isMetro ? br.metro : br.other;
      }
    }
    return 0;
  }

  function stampDuty(price) {
    var list = CFG.misc.stampDuty;
    for (var i = 0; i < list.length; i++) {
      if (list[i].upto === null || price <= list[i].upto) return list[i].amount;
    }
    return 0;
  }

  function calc() {
    var price = parseFloat($('#cPrice').value) * 100000000;      // 억 단위 입력
    var stdRaw = $('#cStd').value.trim();
    var area = parseFloat($('#cArea').value) || 0;
    var isMetro = $('#cRegion').value === 'metro';
    var discount = parseFloat($('#cDiscount').value) || 0;

    var out = $('#calcOut');
    if (!price || price <= 0) {
      out.innerHTML = '<p class="calc__empty">분양가를 입력하면 예상 비용이 계산됩니다.</p>';
      return;
    }

    // 시가표준액: 입력이 없으면 분양가의 70%로 추정
    var estimated = !stdRaw;
    var std = estimated ? price * 0.7 : parseFloat(stdRaw) * 100000000;

    // 취득세
    var aRate = acqRate(price);
    var acq = price * aRate / 100;
    var edu = acq * CFG.acquisitionTax.eduTaxRatio;
    var rural = (area > CFG.acquisitionTax.ruralTaxExemptArea)
      ? price * CFG.acquisitionTax.ruralTaxRate / 100 : 0;
    var taxTotal = acq + edu + rural;

    // 국민주택채권
    var bRate = bondRate(std, isMetro);
    var bondBuy = std * bRate / 100;
    var bondLoss = bondBuy * discount / 100;   // 즉시매도 시 실부담

    // 부대비용
    var stamp = stampDuty(price);
    var fee = CFG.misc.registrationFee + CFG.misc.certFee;

    var total = taxTotal + bondLoss + stamp + fee;

    out.innerHTML =
      '<table class="calc__table">' +
        '<tbody>' +
          row('취득세', aRate + '%', acq) +
          row('지방교육세', '취득세의 10%', edu) +
          (rural ? row('농어촌특별세', '전용 ' + area + '㎡ · 0.2%', rural)
                 : row('농어촌특별세', '전용 85㎡ 이하 비과세', 0)) +
          '<tr class="sum"><th>세금 소계</th><td></td><td>' + won(taxTotal) + '</td></tr>' +
          row('국민주택채권 매입', (bRate ? bRate + '% · 시가표준액 ' + eok(std) : '매입 면제'), bondBuy) +
          row('채권 즉시매도 손실', '할인율 ' + discount + '%', bondLoss) +
          row('인지세', stampDuty(price) ? '' : '주택 1억 이하 비과세', stamp) +
          row('등기신청수수료 · 증명서', '', fee) +
          '<tr class="total"><th>예상 합계</th><td></td><td>' + won(total) + '</td></tr>' +
        '</tbody>' +
      '</table>' +
      '<p class="calc__note">' +
        (estimated
          ? '시가표준액을 입력하지 않아 <b>분양가의 70%</b>로 추정했습니다. 공시가격을 넣으면 정확해집니다.<br>'
          : '') +
        '채권 할인율은 매일 변동합니다. 기준일 <b>' + esc(CFG.bond.rateDate) + '</b>.<br>' +
        '<b>1주택 유상취득 기준</b>이며 다주택·법인·조정대상지역 중과는 반영하지 않았습니다. ' +
        '법무 수수료는 별도이며, 실제 납부액과 다를 수 있는 <b>참고용 추정치</b>입니다.' +
      '</p>';

    function row(label, memo, amount) {
      return '<tr><th>' + esc(label) + '</th><td>' + esc(memo || '') + '</td><td>' +
             (amount ? won(amount) : '—') + '</td></tr>';
    }
  }

  /* =======================================================
     2) 준비서류 체크리스트
     ======================================================= */
  function renderDocs() {
    var d = CFG.docs;
    var who = $('#dWho').value;
    var loan = $('#dLoan').value;
    var type = $('#dType').value;

    var groups = [
      { title: '공통 서류', items: d.common },
      { title: d.byCase[who].label, items: d.byCase[who].items },
      { title: d.byLoan[loan].label, items: d.byLoan[loan].items },
      { title: d.byType[type].label, items: d.byType[type].items }
    ].filter(function (g) { return g.items && g.items.length; });

    var n = 0;
    $('#docsOut').innerHTML = groups.map(function (g) {
      return '<div class="docs__group">' +
        '<h4>' + esc(g.title) + '</h4>' +
        '<ul>' + g.items.map(function (it) {
          n++;
          var id = 'doc' + n;
          return '<li>' +
            '<input type="checkbox" id="' + id + '">' +
            '<label for="' + id + '"><b>' + esc(it.name) + '</b>' +
            (it.detail ? '<span>' + esc(it.detail) + '</span>' : '') + '</label>' +
          '</li>';
        }).join('') + '</ul>' +
      '</div>';
    }).join('');

    $('#docsCount').textContent = n;
  }

  /* =======================================================
     3) 타임라인 · 4) 단지 목록
     ======================================================= */
  function renderTimeline() {
    var el = $('[data-timeline]');
    if (!el) return;
    el.innerHTML = CFG.timeline.map(function (t, i) {
      return '<li class="tl__item">' +
        '<span class="tl__no">' + String(i + 1).padStart(2, '0') + '</span>' +
        '<span class="tl__body"><b>' + esc(t.step) + '</b>' +
        '<span>' + esc(t.desc) + '</span></span>' +
      '</li>';
    }).join('');
  }

  function renderComplexes() {
    var el = $('[data-complexes]');
    if (!el) return;
    el.innerHTML = CFG.complexes.map(function (c) {
      return '<li>' + esc(c) + '</li>';
    }).join('');
    var cnt = $('[data-complex-count]');
    if (cnt) cnt.textContent = CFG.complexes.length;
  }

  /* =======================================================
     초기화
     ======================================================= */
  fetch('data/registry.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      CFG = d;

      var disc = $('#cDiscount');
      if (disc && !disc.value) disc.value = d.bond.rate;

      $$('#calcForm input, #calcForm select').forEach(function (el) {
        el.addEventListener('input', calc);
        el.addEventListener('change', calc);
      });
      $$('#docsForm select').forEach(function (el) {
        el.addEventListener('change', renderDocs);
      });

      var pr = $('#btnPrintDocs');
      if (pr) pr.addEventListener('click', function () { window.print(); });

      calc();
      renderDocs();
      renderTimeline();
      renderComplexes();

      // 접수 위저드(registry-apply.js)가 같은 기준으로 계산할 수 있게 노출
      window.JLRegCalc = {
        cfg: CFG,
        acqRate: acqRate,
        bondRate: bondRate,
        stampDuty: stampDuty
      };
      document.dispatchEvent(new CustomEvent('jlreg:ready'));
    })
    .catch(function (err) {
      console.error('[portal]', err);
      var out = $('#calcOut');
      if (out) out.innerHTML = '<p class="calc__empty">계산 기준 정보를 불러오지 못했습니다. (' +
                               esc(err.message) + ')</p>';
    });
})();
