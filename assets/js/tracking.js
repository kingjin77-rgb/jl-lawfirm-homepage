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
     tracking.html?demo=1 로 들어오면 서버 대신 data/demo-track.json 을 본다.
     예전 체험 화면은 아무 값이나 넣어도 결과가 나와서 시험이 되지 않았다.
     이제 서버와 똑같이 이름·생년월일을 해시로 바꿔 대조한다.
     맞아야 조회되고, 생년월일 한 자리만 틀려도 막힌다. 공동명의는 두 분 모두 된다.
     자료는 가짜 세대다. 실제 입주민 정보가 아니다. */
  var DEMO = qs.get('demo') === '1';
  var demoDb = null;

  function loadDemo() {
    if (demoDb) return Promise.resolve(demoDb);
    return fetch('data/demo-track.json', { cache: 'no-cache' })
      .then(function (r) { return r.json(); })
      .then(function (d) { demoDb = d; return d; });
  }

  function sha256hex(text) {
    return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buf) {
      return Array.prototype.map.call(new Uint8Array(buf), function (x) {
        return x.toString(16).padStart(2, '0');
      }).join('');
    });
  }

  /** lookup.php 와 같은 판정. 어느 칸이 틀렸는지는 알려주지 않는다. */
  function demoLookup(p) {
    return loadDemo().then(function (d) {
      var list = d.complexes[p.complex] || [];
      return sha256hex(d.salt + '|' + p.name + '|' + p.birth).then(function (vh) {
        var u = list.filter(function (x) {
          return x.dong === p.dong && x.ho === p.ho && x.vhash.indexOf(vh) >= 0;
        })[0];
        if (!u) {
          return { ok: false, message: '조회되지 않았습니다. 동과 호, 계약자 성함과 생년월일을 다시 확인해 주십시오. ' +
                                        '계약자가 다른 분 명의인 경우에도 조회되지 않습니다.' };
        }
        return { ok: true, complex: p.complex, dong: u.dong, ho: u.ho, step: u.step, at: u.at,
                 memo: u.memo, total: u.total, paid: u.paid, diff: u.diff, paidAt: u.paidAt, items: u.items };
      });
    });
  }

  /** 체험 화면에서 바로 눌러 볼 세대 목록 */
  function drawGuide() {
    loadDemo().then(function (d) {
      var box = document.createElement('section');
      box.className = 'trk__guide';
      box.innerHTML = '<h3>체험용 세대로 조회해 보십시오</h3>' +
        '<p>' + lines('아래 세대를 누르면 칸이 채워집니다. 생년월일을 한 자리 바꾸면 막히는 것도 보실 수 있습니다.') + '</p>' +
        '<ul>' + d.guide.map(function (g, i) {
          return '<li><button type="button" data-g="' + i + '">' +
            '<b>' + esc(g.complex) + ' ' + esc(g.dong) + '동 ' + esc(g.ho) + '호</b>' +
            '<span>' + esc(g.name) + '  ' + esc(g.birth) + '</span>' +
            '<em>' + esc(g.hint) + '</em>' +
            (g.joint ? '<i>공동명의인 ' + esc(g.joint[0]) + ' ' + esc(g.joint[1]) + ' 으로도 조회됩니다</i>' : '') +
            '</button></li>';
        }).join('') + '</ul>';
      var form = $('form');
      form.insertBefore(box, form.querySelector('.trk__title'));
      box.querySelectorAll('[data-g]').forEach(function (b) {
        b.addEventListener('click', function () {
          var g = d.guide[Number(b.dataset.g)];
          $('inComplex').value = g.complex;
          $('fComplex').hidden = false;
          $('atComplex').hidden = true;
          $('inDong').value = g.dong; $('inHo').value = g.ho;
          $('inName').value = g.name; $('inBirth').value = g.birth;
          err('');
          $('btnGo').scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
      });
    });
  }

  /* ── 거들기 ───────────────────────────────── */

  /** 문장마다 줄을 바꾼다. "니다." "주십시오." "해요." 뒤에서 끊는다. */
  function lines(text) {
    return String(text || '').replace(/\s*(문의 [\d-]+)\s*$/, '\n$1').split(/(?<=[다오요]\.)\s+|\n/).filter(Boolean).map(function (t) {
      return '<span class="s">' + esc(t) + '</span>';
    }).join('');
  }

  function err(text) {
    var n = $('err');
    if (!text) { n.hidden = true; n.innerHTML = ''; return; }
    n.hidden = false;
    n.innerHTML = lines(text);
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
    var src = DEMO
      ? loadDemo().then(function (d) { return Object.keys(d.complexes); })
      : fetch('data/registry.json', { cache: 'no-cache' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            var list = (d.tracking && d.tracking.complexes || []).map(function (c) { return c.name; });
            if (!list.length) list = d.complexes || [];
            return list;
          });
    return src
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

    if (DEMO) {
      demoLookup(payload).then(function (res) {
        if (res.ok) show(res); else err(res.message);
      }).catch(function () { err('체험 자료를 불러오지 못했습니다. 새로고침해 주십시오.'); });
      return;
    }

    if (!ENDPOINT) {
      err('온라인 조회는 준비 중입니다. 문의 1899-4252');
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
          : '조회되지 않았습니다. 동과 호, 계약자 성함과 생년월일을 다시 확인해 주십시오.');
      })
      .catch(function () {
        err('지금 조회가 되지 않습니다. 잠시 뒤 다시 시도해 주십시오. 문의 1899-4252');
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
      $('rMemoText').innerHTML = lines(res.memo);
    } else {
      $('rMemo').hidden = true;
    }

    showCost(res);

    $('rSteps').innerHTML = STEPS.map(function (s, k) {
      var cls = k < i ? 'is-done' : (k === i ? 'is-now' : '');
      return '<li class="' + cls + '">' +
        '<b>' + esc(s.n) + '</b>' +
        '<span class="d">' + lines(s.d) + '</span>' +
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
      b.innerHTML = lines('체험 화면입니다. 가짜 세대로 조회해 봅니다. 성함과 생년월일이 맞아야 조회됩니다. 한 자리만 틀려도 막힙니다.');
      $('form').insertBefore(b, $('form').firstChild);
      drawGuide();
    }

    loadComplexes();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})();
