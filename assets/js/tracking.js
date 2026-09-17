/* 등기 진행 조회 — 고객용

   옛 시스템은 단지 22개 드롭다운에서 우리 아파트를 찾는 데서 시작했다.
   문자로 링크를 보낼 때 단지를 미리 박아 보내면 그 단계가 통째로 사라진다.
     tracking.html?c=보평역%20서희스타힐스
   그러면 고객은 동·호·이름·생년월일 네 칸만 채운다.

   결과도 글자 한 줄이 아니라 여덟 단계 중 어디인지 보여준다.
   고객이 정작 궁금한 것은 "지금 어디"가 아니라 "다음에 뭘 해야 하고 언제 끝나나"이다.

   서버 주소(ENDPOINT)가 비어 있으면 조회를 시도하지 않고 전화 안내로 넘긴다.
   도메인을 붙이는 순간 화면이 깨지지 않게 하려는 것이다.
   서버가 생기면 아래 한 줄만 채우면 그대로 붙는다.
*/
(function () {
  'use strict';

  var ENDPOINT = '';          // 예: 'https://www.jllawfirm.co.kr/track/api/lookup.php'

  // 실제 업무의 여덟 단계. 관리자 화면·엑셀과 같은 순서다.
  var STEPS = [
    { n: '서류수령',            d: '등기에 필요한 서류를 받았습니다.' },
    { n: '취득세신고',          d: '계약서를 검인하고 취득세를 신고합니다.' },
    { n: '등기비용통보',        d: '납부하실 등기비용을 문자로 알려드립니다.' },
    { n: '등기비입금확인',      d: '보내주신 등기비용이 들어온 것을 확인했습니다.' },
    { n: '건설사 등기서류수령', d: '건설사에서 등기 서류를 받았습니다. 건설사 일정에 따라 기다리는 구간입니다.' },
    { n: '등기소서류접수',      d: '관할 등기소에 접수했습니다. 대출 세대는 근저당권설정을 함께 넣습니다.' },
    { n: '등기완료',            d: '등기가 끝났습니다. 접수부터 보통 한 달 이상 걸립니다.' },
    { n: '권리증교부',          d: '등기권리증을 보내드리고 비용을 정산합니다. 모든 절차가 끝났습니다.' }
  ];
  var NOT_YET = '접수 전';

  var $ = function (id) { return document.getElementById(id); };
  var qs = new URLSearchParams(location.search);

  /* ── 체험 모드 ─────────────────────────────
     서버가 아직 없어도 화면을 눌러 볼 수 있게 한다.
     tracking.html?demo=1 로 들어오면 아무 값이나 넣어도 결과가 나온다.
     실제 자료가 아니라는 표시를 화면에 남긴다. */
  var DEMO = qs.get('demo') === '1';

  /** 체험용 명세 — 실제 엑셀 한 세대의 비율을 흉내 낸다. 합이 합계와 딱 맞게 끝을 맞춘다. */
  function demoItems(total) {
    var parts = [
      ['취득세', .62], ['이전채권', .21], ['설정채권', .09], ['인지대', 0], ['증지대', .004],
      ['경유증표', .001], ['신탁말소', 0], ['제증명', .002], ['보수료', .05], ['부가세', .005],
      ['기타(교통비 등)', .011], ['송달료', .002], ['감면수수료', 0]
    ];
    var used = 0;
    var out = parts.map(function (p, i) {
      var v = Math.round(total * p[1] / 1000) * 1000;
      used += v;
      return { k: p[0], v: v };
    });
    out[0].v += total - used;       // 반올림 오차는 취득세에 얹는다
    return out;
  }

  function demoAnswer(dong, ho) {
    // 같은 동·호면 늘 같은 결과가 나오게 한다. 눌러 보며 설명하기 편하다.
    var seed = (Number(dong) || 0) * 7 + (Number(ho) || 0) * 13;
    var i = seed % STEPS.length;
    var total = 2400000 + (seed % 60) * 84000;
    var paid = i >= 3 ? total + ((seed % 7) - 2) * 62000 : 0;
    var d = new Date();
    d.setDate(d.getDate() - (seed % 20));
    return {
      ok: true,
      complex: $('inComplex').value || '보평역 서희스타힐스',
      dong: dong, ho: ho,
      step: STEPS[i].n,
      at: d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') +
          '-' + String(d.getDate()).padStart(2, '0'),
      memo: (i === 2) ? '주민등록등본에 주소 변동 이력이 빠져 있습니다. 다시 발급받아 보내주십시오.' : '',
      total: total, paid: paid, diff: paid ? paid - total : 0,
      items: demoItems(total)
    };
  }

  /* ── 거들기 ───────────────────────────────── */

  function err(text) {
    var n = $('err');
    if (!text) { n.hidden = true; n.textContent = ''; return; }
    n.hidden = false;
    n.textContent = text;
    n.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function onlyDigits(el) {
    el.addEventListener('input', function () {
      var v = el.value.replace(/[^0-9]/g, '');
      if (v !== el.value) el.value = v;
    });
  }

  /* ── 단지 목록 ─────────────────────────────
     registry.json 에 이미 단지가 있다. 따로 관리하지 않는다. */
  function loadComplexes() {
    var pre = qs.get('c');
    return fetch('data/registry.json', { cache: 'no-cache' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var list = (d.tracking && d.tracking.complexes || []).map(function (c) { return c.name; });
        if (!list.length) list = d.complexes || [];
        return list;
      })
      .catch(function () { return []; })
      .then(function (list) {
        var sel = $('inComplex');
        sel.innerHTML = '<option value="">— 아파트를 선택해 주십시오 —</option>' +
          list.map(function (n) { return '<option>' + esc(n) + '</option>'; }).join('');
        // 문자로 받은 링크에 단지가 들어 있으면 고르는 단계를 없앤다
        if (pre && list.indexOf(pre) >= 0) {
          sel.value = pre;
          $('fComplex').hidden = true;
          $('atComplex').hidden = false;
          $('atComplex').textContent = pre;
          $('inDong').focus();
        }
      });
  }

  /* ── 조회 ─────────────────────────────────── */

  function validate() {
    var dong = $('inDong').value.trim(),
        ho = $('inHo').value.trim(),
        name = $('inName').value.trim(),
        birth = $('inBirth').value.trim();

    if (!$('inComplex').value) return '아파트를 선택해 주십시오.';
    if (!dong) return '동을 입력해 주십시오.';
    if (!ho) return '호를 입력해 주십시오.';
    if (!name) return '이름을 입력해 주십시오.';
    if (birth.length !== 6) return '생년월일을 6자리로 입력해 주십시오. 1990년 3월 5일이면 900305입니다.';
    return null;
  }

  function go() {
    var bad = validate();
    if (bad) { err(bad); return; }
    err('');

    var payload = {
      complex: $('inComplex').value,
      dong: $('inDong').value.trim(),
      ho: $('inHo').value.trim(),
      name: $('inName').value.trim(),
      birth: $('inBirth').value.trim()
    };

    if (DEMO) { show(demoAnswer(payload.dong, payload.ho)); return; }

    if (!ENDPOINT) {
      err('온라인 조회는 준비 중입니다. 등기센터 1899-4252로 연락 주시면 바로 확인해 드립니다.');
      return;
    }

    var btn = $('btnGo');
    btn.disabled = true;
    btn.textContent = '조회하는 중…';

    fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.ok) { show(res); return; }
        err(res && res.message
          ? res.message
          : '조회되지 않았습니다. 동·호와 계약자 성함, 생년월일을 다시 확인해 주십시오.');
      })
      .catch(function () {
        err('지금 조회가 되지 않습니다. 잠시 뒤 다시 시도하시거나 1899-4252로 연락 주십시오.');
      })
      .then(function () {
        btn.disabled = false;
        btn.textContent = '조회하기';
      });
  }

  /* ── 결과 ─────────────────────────────────── */

  function show(res) {
    var i = STEPS.map(function (s) { return s.n; }).indexOf(res.step);
    if (i < 0) i = 0;

    $('rAt').textContent = res.complex + (DEMO ? '  ·  체험 화면' : '');
    $('rTitle').textContent = res.dong + '동 ' + res.ho + '호';
    $('rStep').textContent = res.step;
    $('rDate').textContent = res.at ? res.at + ' 기준' : '';

    var pct = Math.round((i + 1) / STEPS.length * 100);
    $('rBar').style.width = pct + '%';
    $('rPct').textContent = STEPS.length + '단계 중 ' + (i + 1) + '번째 · ' + pct + '%';

    if (res.memo) {
      $('rMemo').hidden = false;
      $('rMemoText').textContent = res.memo;
    } else {
      $('rMemo').hidden = true;
    }

    showCost(res);

    $('rSteps').innerHTML = STEPS.map(function (s, k) {
      var cls = k < i ? 'is-done' : (k === i ? 'is-now' : '');
      return '<li class="' + cls + '">' +
        '<b>' + esc(s.n) + '</b>' +
        '<span>' + esc(s.d) + '</span>' +
        (k === i ? '<em>지금 여기</em>' : '') +
        '</li>';
    }).join('');

    $('form').hidden = true;
    $('result').hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /** 등기비용. 고객이 가장 자주 묻는 것이 "얼마고 얼마 냈나"다. */
  function showCost(res) {
    var total = Number(res.total) || 0, paid = Number(res.paid) || 0;
    if (!total && !paid) { $('rCost').hidden = true; return; }

    $('rCost').hidden = false;

    // 항목별 명세 — 0원인 항목은 흐리게 두되 빼지는 않는다.
    // "감면 수수료 0원" 을 보고 안심하는 고객이 있다.
    var items = Array.isArray(res.items) ? res.items : [];
    if (items.length) {
      $('cBill').hidden = false;
      var nz = items.filter(function (it) { return Number(it.v); }).length;
      $('cBillN').textContent = '· ' + nz + '개 항목';
      $('cItems').innerHTML = items.map(function (it) {
        var v = Number(it.v) || 0;
        return '<tr class="' + (v ? '' : 'zero') + '"><th>' + esc(it.k) + '</th>' +
          '<td>' + v.toLocaleString('ko-KR') + '원</td></tr>';
      }).join('');
    } else {
      $('cBill').hidden = true;
    }

    $('cTotal').textContent = total ? total.toLocaleString('ko-KR') + '원' : '아직 산정 전';
    $('cPaid').textContent = paid ? paid.toLocaleString('ko-KR') + '원' : '아직 입금 전';

    var diff = Number(res.diff);
    if (!isFinite(diff)) diff = paid ? paid - total : 0;

    if (!paid || diff === 0) {
      $('cDiffRow').hidden = true;
      $('cNote').textContent = paid ? '정산이 맞아떨어졌습니다.' : '';
      return;
    }
    $('cDiffRow').hidden = false;
    $('cDiffLbl').textContent = diff > 0 ? '돌려드릴 금액' : '더 내셔야 할 금액';
    $('cDiff').textContent = Math.abs(diff).toLocaleString('ko-KR') + '원';
    $('cDiff').className = diff > 0 ? 'is-back' : 'is-more';
    $('cNote').textContent = diff > 0
      ? '정산 후 남은 금액은 알려주신 계좌로 돌려드립니다.'
      : '부족한 금액은 안내 문자의 계좌로 보내주시면 됩니다.';
  }

  /* ── 이어 붙이기 ───────────────────────────── */

  function init() {
    onlyDigits($('inDong'));
    onlyDigits($('inHo'));
    onlyDigits($('inBirth'));

    $('btnGo').addEventListener('click', go);
    ['inDong', 'inHo', 'inName', 'inBirth'].forEach(function (id) {
      $(id).addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); go(); }
      });
    });

    $('btnAgain').addEventListener('click', function () {
      $('result').hidden = true;
      $('form').hidden = false;
      $('inDong').value = ''; $('inHo').value = '';
      $('inName').value = ''; $('inBirth').value = '';
      err('');
      $('inDong').focus();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    if (DEMO) {
      var b = document.createElement('p');
      b.className = 'trk__demo';
      b.textContent = '체험 화면입니다. 실제 자료가 아니며, 아무 값이나 넣어도 결과가 나옵니다.';
      $('form').insertBefore(b, $('form').firstChild);
    }

    loadComplexes();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})();
