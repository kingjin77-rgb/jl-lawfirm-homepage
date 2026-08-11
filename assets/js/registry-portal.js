/* 제이엘 등기센터 포털 — data/registry.json
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

  /* 인지세 — 인지세법 별표. 다만 주택의 소유권 이전 증서는 1억원 이하면 비과세다(제6조).
     예전에는 이 주택 특례를 상가·토지에도 적용해 8천만원짜리 상가가 0원으로 나왔다. */
  function stampDuty(price, isHouse) {
    var ex = CFG.misc.stampDutyHouseExempt;
    if (isHouse && ex && price <= ex.upto) return 0;
    var list = CFG.misc.stampDuty;
    for (var i = 0; i < list.length; i++) {
      if (list[i].upto === null || price <= list[i].upto) return list[i].amount;
    }
    return 0;
  }

  // 취득 원인에 따라 안 쓰는 입력칸을 감춘다
  function syncCalcFields() {
    var cause = $('#cCause') ? $('#cCause').value : 'purchase_house';
    $$('[data-c-when]').forEach(function (el) {
      el.style.display =
        el.getAttribute('data-c-when').split(' ').indexOf(cause) >= 0 ? '' : 'none';
    });
    var pl = $('[data-c-pricelabel]');
    if (pl) {
      pl.textContent = {
        purchase_house: '취득가액 (분양가)',
        purchase_other: '취득가액 (매매가)',
        original: '신축 건물 시가표준액',
        inherit: '시가표준액 (공시가격)',
        gift: '시가표준액 (공시가격)',
        mortgage: '채권최고액',
        landright: '대지권 시가표준액'
      }[cause] || '취득가액';
    }
  }

  function calc() {
    var T = CFG.tax;
    var cause = $('#cCause') ? $('#cCause').value : 'purchase_house';
    var price = parseFloat($('#cPrice').value) * 100000000;      // 억 단위 입력
    var stdRaw = $('#cStd').value.trim();
    var area = parseFloat($('#cArea').value) || 0;
    var isMetro = $('#cRegion').value === 'metro';
    // 음수를 넣으면 손실이 이익으로 뒤집혀 합계가 깎인다. 0 아래로는 내려가지 않게 한다
    var discount = Math.max(0, parseFloat($('#cDiscount').value) || 0);

    var out = $('#calcOut');
    if (!price || price <= 0) {
      out.innerHTML = '<p class="calc__empty">금액을 입력하면 예상 비용이 계산됩니다.</p>';
      return;
    }

    // 0 을 넣어도 값이 있는 것으로 보면 과세표준이 0 이 되어 세금이 전부 0 원으로 나온다
    var stdNum = parseFloat(stdRaw);
    var estimated = !(stdNum > 0);
    var std = estimated ? price * 0.7 : stdNum * 100000000;
    var rows = [];
    var notes = [];
    var total = 0;

    function row(label, memo, amount) {
      rows.push('<tr><th>' + esc(label) + '</th><td>' + esc(memo || '') + '</td><td>' +
                (amount ? won(amount) : '—') + '</td></tr>');
    }

    /* ---- 근저당권설정 · 대지권: 등록면허세 계열 ---- */
    if (cause === 'mortgage' || cause === 'landright') {
      var m = cause === 'mortgage' ? T.mortgage : T.landRight;
      var reg = price * m.rate / 100;
      var regEdu = reg * m.eduRatio;
      var fee0 = CFG.misc.registrationFee + CFG.misc.certFee;
      total = reg + regEdu + fee0;
      row('등록면허세', m.rate + '%' + (cause === 'mortgage' ? ' · 채권최고액 기준' : ''), reg);
      row('지방교육세', '등록면허세의 20%', regEdu);
      row('등기신청수수료 · 증명서', '', fee0);
      if (cause === 'mortgage') {
        notes.push('근저당권설정 시 국민주택채권 매입 의무가 별도로 있을 수 있습니다(채권최고액 2천만원 이상). 정확한 매입액은 담당자가 안내합니다.');
      }
      if (cause === 'landright' && T.landRight.note) notes.push(T.landRight.note);
      render();
      return;
    }

    /* ---- 취득세 계열 ---- */
    var c = T.causes[cause];
    var aRate, rateMemo;
    var heavyApplied = false;      // 다주택·법인 중과세율이 적용됐는지 (지방교육세 계산에 쓴다)

    if (cause === 'purchase_house') {
      var houses = $('#cHouses') ? $('#cHouses').value : '1';
      var adjusted = $('#cAdjusted') && $('#cAdjusted').value === 'yes';
      var heavy = c.heavy[adjusted ? 'adjusted' : 'normal'][houses];
      if (heavy != null) {
        aRate = heavy;
        heavyApplied = true;
        rateMemo = (adjusted ? '조정대상지역 · ' : '') +
                   (houses === 'corp' ? '법인' : houses + '주택') + ' 중과 ' + aRate + '%';
        notes.push('다주택·법인 중과세율이 적용된 추정입니다. 일시적 2주택 등 예외는 반영되지 않았습니다.');
      } else {
        aRate = acqRate(price);
        rateMemo = aRate + '%';
      }
    } else if (cause === 'inherit') {
      var reduced = $('#cInheritReduced') && $('#cInheritReduced').value === 'yes';
      aRate = reduced ? c.reducedRate : c.rate;
      rateMemo = aRate + '%' + (reduced ? ' · ' + c.reducedLabel : '');
      if (estimated) { std = price; }           // 상속·증여는 입력값 자체가 시가표준액
      price = std;
    } else if (cause === 'gift') {
      var adjG = $('#cAdjusted') && $('#cAdjusted').value === 'yes';
      var heavyGift = adjG && price >= 300000000;
      aRate = heavyGift ? c.heavyRate : c.rate;
      rateMemo = aRate + '%' + (heavyGift ? ' · ' + c.heavyLabel : '');
      if (estimated) { std = price; }
      price = std;
    } else {
      aRate = c.rate;
      rateMemo = aRate + '%';
      if (cause === 'original' && estimated) { std = price; price = std; }
    }

    var acq = price * aRate / 100;

    /* 감면 */
    var reliefAmt = 0, reliefMemo = '';
    if (cause === 'purchase_house') {
      var rSel = $('#cRelief') ? $('#cRelief').value : '';
      if (rSel === 'firstHome' && price <= T.relief.firstHome.priceLimit) {
        reliefAmt = Math.min(acq, T.relief.firstHome.maxAmount);
        reliefMemo = T.relief.firstHome.label;
      } else if (rSel === 'smallHouse' &&
                 area <= T.relief.smallHouse.areaLimit &&
                 price <= T.relief.smallHouse.priceLimit) {
        reliefAmt = acq;
        reliefMemo = T.relief.smallHouse.label;
      } else if (rSel) {
        notes.push('선택하신 감면의 금액·면적 요건을 충족하지 않아 감면을 적용하지 않았습니다.');
      }
    }
    var acqAfter = acq - reliefAmt;

    /* 지방교육세 — 지방세법 제151조.
       원칙은 (취득세율 − 중과기준세율 2%) × 20% 다.
       주택을 사고파는 경우만 예외로 (세율 × 50%) × 20% = 취득세의 10% 이고,
       다주택 중과일 때는 표준세율 4% 를 기준으로 보아 0.4% 로 고정된다.
       예전에는 모든 원인에 취득세의 10% 를 곱해, 세율이 4% 가 아닌
       상속·증여·원시취득에서 금액이 실제보다 크게 나왔다. */
    var edu;
    if (c.eduMode === 'house') {
      edu = heavyApplied
        ? price * (4 - 2) * 0.2 / 100
        : acqAfter * CFG.acquisitionTax.eduTaxRatio;
    } else {
      edu = price * Math.max(0, aRate - 2) * 0.2 / 100;
    }

    /* 농어촌특별세 — 국민주택규모(전용 85㎡ 이하) 주택은 비과세다(농어촌특별세법 제4조).
       감면분에 붙는 20% 도 마찬가지로 비과세이므로 함께 막는다. */
    var smallHouse = area > 0 && area <= T.ruralTaxExemptArea;
    var rural = 0;
    if (!smallHouse) {
      if (c.kind === 'house') {
        rural = (area > T.ruralTaxExemptArea) ? price * T.ruralTaxRate / 100 : 0;
      } else if (c.ruralAlways) {
        rural = price * T.ruralTaxRate / 100;
        if (!area) notes.push('전용면적을 넣으면 국민주택규모(85㎡ 이하) 비과세까지 반영해 계산합니다.');
      }
      if (reliefAmt > 0) {
        rural += reliefAmt * 0.2;               // 감면분 농특세 20%
        notes.push('감면받은 취득세의 20%는 농어촌특별세로 부과됩니다.');
      }
    }
    var taxTotal = acqAfter + edu + rural;

    var bRate = bondRate(std, isMetro);
    var bondBuy = std * bRate / 100;
    var bondLoss = bondBuy * discount / 100;

    var stamp = (cause === 'inherit' || cause === 'gift') ? 0 : stampDuty(price, c.kind === 'house');
    var fee = CFG.misc.registrationFee + CFG.misc.certFee;

    total = taxTotal + bondLoss + stamp + fee;

    row('취득세', rateMemo, acq);
    if (reliefAmt) row('감면 (−)', reliefMemo, -reliefAmt);
    row('지방교육세', '', edu);
    row('농어촌특별세', rural ? '' : (smallHouse ? '전용 85㎡ 이하 비과세' : ''), rural);
    rows.push('<tr class="sum"><th>세금 소계</th><td></td><td>' + won(taxTotal) + '</td></tr>');
    row('국민주택채권 매입', (bRate ? bRate + '% · 시가표준액 ' + eok(std) : '매입 면제'), bondBuy);
    row('채권 즉시매도 손실', '할인율 ' + discount + '%', bondLoss);
    row('인지세', stamp ? '' :
        (cause === 'inherit' || cause === 'gift') ? '계약서 없음 · 비과세'
      : (c.kind === 'house') ? '주택 1억 이하 비과세'
      : '1천만원 이하 비과세', stamp);
    row('등기신청수수료 · 증명서', '', fee);

    if (estimated && (cause === 'purchase_house' || cause === 'purchase_other')) {
      notes.unshift('시가표준액을 입력하지 않아 <b>취득가액의 70%</b>로 추정했습니다. 공시가격을 넣으면 정확해집니다.');
    }
    notes.push('채권 할인율은 매일 변동합니다. 기준일 <b>' + esc(CFG.bond.rateDate) + '</b>.');
    notes.push('법무 수수료는 별도이며, 실제 납부액과 다를 수 있는 <b>참고용 추정치</b>입니다. 정확한 금액은 상담 시 안내해 드립니다.');

    render();

    function render() {
      out.innerHTML =
        '<table class="calc__table"><tbody>' + rows.join('') +
        '<tr class="total"><th>예상 합계</th><td></td><td>' + won(total) + '</td></tr>' +
        '</tbody></table>' +
        '<p class="calc__note">' + notes.join('<br>') + '</p>';
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
      var causeSel = $('#cCause');
      if (causeSel) {
        causeSel.addEventListener('change', function () { syncCalcFields(); calc(); });
        syncCalcFields();
      }
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
