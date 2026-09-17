/* 설문 관리 — 동의서·투표·탄원서
 *
 * 옛 시스템의 설문은 대부분 입주예정자협의회 일이었다.
 * 도로명주소 선호도 투표, 어린이집 설치 동의서, 명칭변경 동의서, 탄원서 같은 것들.
 * 대상은 단지의 세대이고, 결과보다 "누가 아직 안 냈는지" 가 더 자주 필요했다.
 *
 * 서버가 붙기 전에는 고객이 직접 응답을 낼 수 없다.
 * 그래서 직원이 받은 응답을 넣거나, 단지 플랫폼의 투표 CSV 를 합치는 길을 둔다.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc, $ = JL.$;

  var view = null;   // 보고 있는 설문 id

  var KINDS = {
    agree: '동의 · 부동의',
    one: '하나 고르기',
    many: '여럿 고르기',
    text: '글로 적기'
  };

  function byId(id) {
    return JL.db.surveys.filter(function (s) { return s.id === id; })[0];
  }

  function targetUnits(s) { return JL.units(s.complex); }

  function answeredKeys(s) {
    var m = {};
    s.responses.forEach(function (r) { m[r.unit] = r; });
    return m;
  }

  /* ── 등록·수정 ─────────────────────────────── */
  function qRow(q, i) {
    return '<div class="of-q" data-qi="' + i + '">' +
      '<div class="of-form q">' +
        '<label class="of-fld"><span>질문 ' + (i + 1) + '</span><input class="qT" value="' + esc(q.t) + '" placeholder="예: 국공립 어린이집 설치에 동의하십니까"></label>' +
        '<label class="of-fld"><span>답하는 방식</span><select class="qK">' +
          Object.keys(KINDS).map(function (k) { return '<option value="' + k + '"' + (q.k === k ? ' selected' : '') + '>' + KINDS[k] + '</option>'; }).join('') +
        '</select></label>' +
      '</div>' +
      '<label class="of-fld qO"' + (q.k === 'one' || q.k === 'many' ? '' : ' hidden') + '><span>보기 — 한 줄에 하나씩</span>' +
        '<textarea rows="3" class="qOpt">' + esc((q.o || []).join('\n')) + '</textarea></label>' +
      '<button type="button" class="btn sm danger qDel">이 질문 빼기</button>' +
      '</div>';
  }

  function edit(s) {
    var isNew = !s;
    s = s || { id: JL.uid(), complex: ui.complex(), title: '', org: '', body: '', sign: true,
               questions: [{ t: '', k: 'agree', o: [] }], createdAt: JL.now(), responses: [] };
    var qs = JSON.parse(JSON.stringify(s.questions));

    function draw() {
      $('svQs').innerHTML = qs.map(qRow).join('');
      $('svQs').querySelectorAll('.of-q').forEach(function (row) {
        var i = Number(row.dataset.qi);
        row.querySelector('.qT').addEventListener('input', function () { qs[i].t = this.value; });
        row.querySelector('.qK').addEventListener('change', function () {
          qs[i].k = this.value;
          row.querySelector('.qO').hidden = !(this.value === 'one' || this.value === 'many');
        });
        row.querySelector('.qOpt').addEventListener('input', function () {
          qs[i].o = this.value.split(String.fromCharCode(10)).map(function (x) { return x.trim(); }).filter(Boolean);
        });
        row.querySelector('.qDel').addEventListener('click', function () {
          if (qs.length === 1) return ui.toast('질문은 하나 이상 있어야 합니다.', 'err');
          qs.splice(i, 1); draw();
        });
      });
    }

    ui.dialog({
      title: isNew ? '설문 등록' : '설문 수정',
      body:
        '<div class="of-form two">' +
          '<label class="of-fld"><span>설문 제목</span><input id="svT" value="' + esc(s.title) + '" placeholder="예: 아파트 명칭변경 동의서"></label>' +
          '<label class="of-fld"><span>대상 단지</span><select id="svC">' +
            JL.complexNames().map(function (n) { return '<option' + (n === s.complex ? ' selected' : '') + '>' + esc(n) + '</option>'; }).join('') +
          '</select></label>' +
        '</div>' +
        '<label class="of-fld"><span>주최</span><input id="svO" value="' + esc(s.org) + '" placeholder="예: 힐스테이트 광교산 입주예정자협의회"></label>' +
        '<label class="of-fld"><span>안내문 — 세대에게 보이는 글</span><textarea id="svB" rows="4">' + esc(s.body) + '</textarea></label>' +
        '<label class="of-check"><input type="checkbox" id="svS"' + (s.sign ? ' checked' : '') + '> 서명을 받습니다 (동의서·탄원서)</label>' +
        '<div id="svQs"></div>' +
        '<button type="button" class="btn sm" id="svAddQ">질문 더하기</button>',
      ok: isNew ? '등록' : '반영',
      onOk: function () {
        var t = $('svT').value.trim();
        if (!t) return '설문 제목을 입력해 주십시오.';
        if (!$('svC').value) return '대상 단지가 없습니다. 기본 정보에서 단지를 먼저 추가하십시오.';
        for (var i = 0; i < qs.length; i++) {
          if (!qs[i].t.trim()) return (i + 1) + '번 질문이 비어 있습니다.';
          if ((qs[i].k === 'one' || qs[i].k === 'many') && qs[i].o.length < 2) return (i + 1) + '번 질문의 보기를 두 개 이상 적어 주십시오.';
        }
        if (!isNew && s.complex !== $('svC').value && s.responses.length) {
          return '응답이 들어온 설문은 대상 단지를 바꿀 수 없습니다.';
        }
        s.title = t; s.complex = $('svC').value; s.org = $('svO').value.trim();
        s.body = $('svB').value.trim(); s.sign = $('svS').checked; s.questions = qs;
        s.updatedAt = JL.now();
        if (isNew) JL.db.surveys.unshift(s);
        JL.touch(); ui.go('survey');
        ui.toast('설문을 ' + (isNew ? '등록' : '수정') + '했습니다.', 'ok');
        return true;
      }
    });
    draw();
    $('svAddQ').addEventListener('click', function () { qs.push({ t: '', k: 'agree', o: [] }); draw(); });
  }

  /* ── 응답 넣기 ─────────────────────────────── */
  function answerInput(q, qi, prev) {
    var v = prev ? prev[qi] : null;
    if (q.k === 'agree') {
      return ['동의', '부동의'].map(function (o) {
        return '<label class="of-radio"><input type="radio" name="a' + qi + '" value="' + o + '"' + (v === o ? ' checked' : '') + '> ' + o + '</label>';
      }).join('');
    }
    if (q.k === 'one' || q.k === 'many') {
      var type = q.k === 'one' ? 'radio' : 'checkbox';
      return q.o.map(function (o) {
        var on = q.k === 'one' ? v === o : (v || []).indexOf(o) >= 0;
        return '<label class="of-radio"><input type="' + type + '" name="a' + qi + '" value="' + esc(o) + '"' + (on ? ' checked' : '') + '> ' + esc(o) + '</label>';
      }).join('');
    }
    return '<textarea rows="2" name="a' + qi + '">' + esc(v || '') + '</textarea>';
  }

  function enterAnswer(s) {
    var units = targetUnits(s);
    ui.dialog({
      title: '응답 넣기 · ' + s.title,
      body:
        '<div class="of-form two">' +
          '<label class="of-fld"><span>동</span><input id="anD" inputmode="numeric"></label>' +
          '<label class="of-fld"><span>호</span><input id="anH" inputmode="numeric"></label>' +
        '</div>' +
        s.questions.map(function (q, i) {
          return '<fieldset class="of-fs"><legend>' + (i + 1) + '. ' + esc(q.t) + '</legend>' + answerInput(q, i, null) + '</fieldset>';
        }).join('') +
        '<p class="of-note">서면으로 받은 응답을 옮겨 적을 때 씁니다. 같은 세대를 다시 넣으면 앞 응답을 덮습니다.</p>',
      ok: '넣기',
      onOk: function (box) {
        var key = JL.unitKey($('anD').value, $('anH').value);
        if (!JL.digits($('anD').value) || !JL.digits($('anH').value)) return '동과 호를 입력해 주십시오.';
        if (!JL.db.complexes[s.complex].units[key]) return s.complex + ' 명단에 없는 세대입니다.';
        var ans = [];
        for (var i = 0; i < s.questions.length; i++) {
          var q = s.questions[i], els = box.querySelectorAll('[name="a' + i + '"]');
          if (q.k === 'many') {
            ans.push(Array.prototype.filter.call(els, function (e) { return e.checked; }).map(function (e) { return e.value; }));
          } else if (q.k === 'text') {
            ans.push(els[0].value.trim());
          } else {
            var c = Array.prototype.filter.call(els, function (e) { return e.checked; })[0];
            if (!c) return (i + 1) + '번 질문에 답을 골라 주십시오.';
            ans.push(c.value);
          }
        }
        s.responses = s.responses.filter(function (r) { return r.unit !== key; });
        s.responses.push({ unit: key, answers: ans, at: JL.now(), via: '직원 입력' });
        JL.touch(); ui.go('survey');
        ui.toast(key.replace('-', '동 ') + '호 응답을 넣었습니다. 참여 ' + s.responses.length + '/' + units.length, 'ok');
        return true;
      }
    });
  }

  /** 단지 플랫폼 투표 CSV 합치기 — 동·호와 질문 제목이 같은 칸을 찾는다. */
  function mergeCsv(s) {
    JL.pickFile('.csv', function (file) {
      file.text().then(function (t) {
        var lines = JL.readCsvLines(t);
        if (lines.length < 2) return ui.toast('CSV 에 읽을 줄이 없습니다.', 'err');
        var head = JL.splitCsv(lines[0]);
        var cD = head.indexOf('동'), cH = head.findIndex(function (h) { return h === '호' || h === '호수'; });
        if (cD < 0 || cH < 0) return ui.toast('CSV 에서 동·호 칸을 찾지 못했습니다.', 'err');
        var qCols = s.questions.map(function (q, i) {
          var k = head.findIndex(function (h) { return h && (h === q.t || q.t.indexOf(h) >= 0 || h.indexOf(q.t) >= 0); });
          return k >= 0 ? k : (head.length > 2 + i ? 2 + i : -1);
        });
        var c = JL.db.complexes[s.complex], hit = 0, miss = 0;
        lines.slice(1).forEach(function (line) {
          var r = JL.splitCsv(line), key = JL.unitKey(r[cD], r[cH]);
          if (!c.units[key]) { miss++; return; }
          var ans = s.questions.map(function (q, i) {
            var v = qCols[i] >= 0 ? r[qCols[i]] : '';
            return q.k === 'many' ? v.split(/[\/;·]/).map(function (x) { return x.trim(); }).filter(Boolean) : v;
          });
          s.responses = s.responses.filter(function (x) { return x.unit !== key; });
          s.responses.push({ unit: key, answers: ans, at: JL.now(), via: 'CSV' });
          hit++;
        });
        JL.touch(); ui.go('survey');
        ui.toast('응답 ' + hit + '건을 합쳤습니다' + (miss ? ' · 명단에 없는 세대 ' + miss : '') + '.', 'ok');
      });
    });
  }

  /* ── 결과 ──────────────────────────────────── */
  function renderDetail(el, s) {
    var units = targetUnits(s), done = answeredKeys(s);
    var n = units.length, yes = s.responses.length;
    var not = units.filter(function (u) { return !done[JL.unitKey(u.dong, u.ho)]; });

    var charts = s.questions.map(function (q, qi) {
      if (q.k === 'text') {
        var texts = s.responses.map(function (r) { return r.answers[qi]; }).filter(Boolean);
        return '<article class="of-panel"><h4 class="of-h4">' + (qi + 1) + '. ' + esc(q.t) + '</h4>' +
          (texts.length ? '<ul class="of-texts">' + texts.slice(0, 40).map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>'
                        : '<p class="of-p">적힌 답이 없습니다.</p>') + '</article>';
      }
      var opts = q.k === 'agree' ? ['동의', '부동의'] : q.o;
      var cnt = {};
      s.responses.forEach(function (r) {
        var a = r.answers[qi];
        (Array.isArray(a) ? a : [a]).forEach(function (x) { if (x) cnt[x] = (cnt[x] || 0) + 1; });
      });
      var max = Math.max.apply(null, opts.map(function (o) { return cnt[o] || 0; }).concat([1]));
      return '<article class="of-panel"><h4 class="of-h4">' + (qi + 1) + '. ' + esc(q.t) + '</h4>' +
        '<div class="of-bars">' + opts.map(function (o) {
          var v = cnt[o] || 0, p = yes ? Math.round(v / yes * 100) : 0;
          return '<div class="of-barrow"><span class="l">' + esc(o) + '</span>' +
            '<span class="b"><i style="width:' + (v / max * 100) + '%"></i></span>' +
            '<span class="v"><b>' + v + '</b> · ' + p + '%</span></div>';
        }).join('') + '</div></article>';
    }).join('');

    el.innerHTML =
      '<div class="of-bar"><button type="button" class="btn sm" id="svBack">← 설문 목록</button><span class="of-sp"></span>' +
        '<button type="button" class="btn" id="svEdit">수정</button>' +
        '<button type="button" class="btn" id="svCsvIn">응답 CSV 합치기</button>' +
        '<button type="button" class="btn btn--fill" id="svAns">응답 넣기</button></div>' +
      '<header class="of-svhead"><span class="of-pill">' + esc(s.complex) + '</span>' +
        '<h3>' + esc(s.title) + '</h3>' +
        (s.org ? '<p class="of-p">주최 · ' + esc(s.org) + '</p>' : '') +
        (s.body ? '<p class="of-quote">' + esc(s.body) + '</p>' : '') + '</header>' +
      ui.stats([
        { k: '대상 세대', v: n.toLocaleString() },
        { k: '참여', v: yes.toLocaleString(), s: n ? Math.round(yes / n * 100) + '%' : '', tone: 'ok' },
        { k: '아직 안 낸 세대', v: not.length.toLocaleString(), tone: not.length ? 'warn' : '' }
      ]) +
      '<h3 class="of-h3">결과</h3>' + (yes ? charts : '<div class="of-empty">아직 응답이 없습니다.</div>') +
      '<div class="of-row"><h3 class="of-h3">아직 안 낸 세대</h3><span class="of-sp"></span>' +
        '<button type="button" class="btn sm" id="svNotCsv">CSV</button>' +
        (not.length ? '<button type="button" class="btn sm btn--fill" id="svNotSms">이 세대에 문자 보내기</button>' : '') +
      '</div>' +
      ui.table([
        { t: '동', k: 'dong', cls: 'num' }, { t: '호', k: 'ho', cls: 'num' },
        { t: '명의자', f: function (u) { return esc((u.owners[0] || {}).name); } },
        { t: '휴대폰', f: function (u) { return esc((u.owners[0] || {}).phone) || '<span class="of-pill warn">없음</span>'; } }
      ], not, { empty: '모든 세대가 참여했습니다.', max: 200 });

    $('svBack').addEventListener('click', function () { view = null; ui.go('survey'); });
    $('svEdit').addEventListener('click', function () { edit(s); });
    $('svAns').addEventListener('click', function () { enterAnswer(s); });
    $('svCsvIn').addEventListener('click', function () { mergeCsv(s); });
    $('svNotCsv').addEventListener('click', function () {
      var rows = [['동', '호', '명의자', '휴대폰']];
      not.forEach(function (u) { var o = u.owners[0] || {}; rows.push([u.dong, u.ho, o.name, o.phone]); });
      JL.csv(rows, s.title + '_미참여_' + JL.today() + '.csv');
    });
    if ($('svNotSms')) $('svNotSms').addEventListener('click', function () {
      JL.smsTarget = {
        complex: s.complex,
        label: '「' + s.title + '」 미참여 ' + not.length + '세대',
        keys: not.map(function (u) { return JL.unitKey(u.dong, u.ho); })
      };
      $('ofComplex').value = s.complex;
      ui.go('sms');
    });
  }

  JL.mod('survey', {
    title: '설문',
    icon: '☑',
    lead: '동의서·투표·탄원서를 만들고, 누가 냈고 누가 안 냈는지 봅니다.',

    render: function (el) {
      if (view && byId(view)) return renderDetail(el, byId(view));
      view = null;

      var list = JL.db.surveys;
      el.innerHTML =
        '<div class="of-bar"><span class="of-sp"></span>' +
          (JL.complexNames().length
            ? '<button type="button" class="btn btn--fill" id="svNew">설문 등록</button>'
            : '<span class="of-p">단지가 있어야 설문을 만들 수 있습니다.</span>') +
        '</div>' +
        ui.table([
          { t: '제목', f: function (s) { return '<button type="button" class="of-link" data-view="' + s.id + '">' + esc(s.title) + '</button>'; } },
          { t: '단지', f: function (s) { return esc(s.complex); } },
          { t: '대상', cls: 'num', f: function (s) { return targetUnits(s).length.toLocaleString(); } },
          { t: '참여', cls: 'num', f: function (s) { return '<b>' + s.responses.length.toLocaleString() + '</b>'; } },
          { t: '참여율', f: function (s) {
              var n = targetUnits(s).length, p = n ? Math.round(s.responses.length / n * 100) : 0;
              return '<span class="of-meter"><i style="width:' + p + '%"></i></span> ' + p + '%';
          } },
          { t: '만든 날', cls: 'num', f: function (s) { return esc((s.createdAt || '').slice(0, 10)); } },
          { t: '', f: function (s) {
              return '<button type="button" class="btn sm" data-view="' + s.id + '">결과</button> ' +
                '<button type="button" class="btn sm danger" data-sdel="' + s.id + '">삭제</button>';
          } }
        ], list, { empty: '등록한 설문이 없습니다.' });

      if ($('svNew')) $('svNew').addEventListener('click', function () { edit(null); });
      el.querySelectorAll('[data-view]').forEach(function (b) {
        b.addEventListener('click', function () { view = b.dataset.view; ui.go('survey'); });
      });
      el.querySelectorAll('[data-sdel]').forEach(function (b) {
        b.addEventListener('click', function () {
          var s = byId(b.dataset.sdel);
          ui.dialog({
            title: '설문 삭제',
            body: '<p class="of-p"><b>' + esc(s.title) + '</b> 과 응답 ' + s.responses.length + '건을 지웁니다. 되돌릴 수 없습니다.</p>',
            ok: '지우기',
            onOk: function () {
              JL.db.surveys = JL.db.surveys.filter(function (x) { return x.id !== s.id; });
              JL.touch(); ui.go('survey');
              return true;
            }
          });
        });
      });
    }
  });

})(window);
