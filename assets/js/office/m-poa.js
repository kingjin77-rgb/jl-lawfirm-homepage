/* 위임장 관리 — 단지 플랫폼의 전자 위임장을 받아 온다
 *
 * 위임장은 단지마다 따로 배포하는 입주예정자협의회 플랫폼(jl-demo 계열)에서 받는다.
 * 플랫폼 임원 화면의 「위임장접수내역 CSV」를 그대로 올리면 된다.
 * 플랫폼은 이름을 김○○ 처럼 가려서 내보내므로 동·호로 맞춘다.
 *
 * 등기에서 위임장이 중요한 이유는 하나다. 위임장을 낸 세대가 곧 등기를 맡길 세대다.
 * 그래서 위임장은 냈는데 등기 서류가 안 들어온 세대를 따로 뽑는다.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc, $ = JL.$;

  var show = 'none';   // none | done | gap

  function mergeCsv(cx) {
    JL.pickFile('.csv', function (file) {
      JL.readText(file).then(function (t) {
        var rows = JL.parseCsv(t);
        if (rows.length < 2) return ui.toast('CSV 에 읽을 줄이 없습니다.', 'err');
        var head = rows[0].map(function (h) { return h.replace(/\s+/g, ''); });
        var find = function (re) { return head.findIndex(function (h) { return re.test(h); }); };
        var cD = head.indexOf('동'), cH = head.findIndex(function (h) { return h === '호' || h === '호수'; });
        var cJ = find(/명의/), cA = find(/접수일|일시/);
        if (cD < 0 || cH < 0) {
          return ui.toast('동·호 칸을 찾지 못했습니다. 플랫폼의 위임장접수내역 CSV 가 맞는지 확인해 주십시오.', 'err');
        }
        var c = JL.complex(cx), hit = 0, added = 0, seen = {};
        rows.slice(1).forEach(function (r) {
          // 칸이 모자란 줄도 온다. 없는 칸은 빈칸으로 본다.
          var key = JL.unitKey(r[cD], r[cH]);
          if (!JL.digits(r[cD]) || !JL.digits(r[cH]) || seen[key]) return;
          seen[key] = 1;
          var u = c.units[key];
          if (!u) {
            // 위임장만 먼저 들어오는 세대도 있다. 등기 엑셀보다 협의회가 먼저 움직인다.
            u = JL.newUnit(r[cD], r[cH]);
            c.units[key] = u;
            added++;
          }
          u.poa = { at: (cA >= 0 && r[cA]) || JL.today(), joint: cJ >= 0 && (r[cJ] || '').indexOf('공동') >= 0 };
          hit++;
        });
        JL.touch(); ui.drawComplexes(cx); ui.go('poa');
        ui.toast('위임장 ' + hit + '세대를 반영했습니다' +
          (added ? ' · 등기 명단에 없던 ' + added + '세대는 새로 만들었습니다' : '') + '.', 'ok');
      }).catch(function () { ui.toast('CSV 를 읽지 못했습니다.', 'err'); });
    });
  }

  JL.mod('poa', {
    title: '위임장',
    icon: '✍',
    lead: '단지 플랫폼에서 받은 전자 위임장을 등기 명단과 맞춰 봅니다.',

    render: function (el, cx) {
      if (ui.needComplex(el, cx)) return;
      var units = JL.units(cx);
      var done = units.filter(function (u) { return u.poa; });
      var joint = done.filter(function (u) { return u.poa.joint; }).length;
      var none = units.filter(function (u) { return !u.poa; });
      // 위임장은 냈는데 등기 서류가 아직 안 들어온 세대 — 가장 먼저 연락할 곳
      var gap = done.filter(function (u) { return u.step === JL.NOT_YET; });
      var pct = units.length ? Math.round(done.length / units.length * 100) : 0;

      // 동별 접수율
      var byDong = {};
      units.forEach(function (u) {
        var d = byDong[u.dong] = byDong[u.dong] || { n: 0, y: 0 };
        d.n++; if (u.poa) d.y++;
      });
      var dongs = Object.keys(byDong).sort(function (a, b) { return Number(a) - Number(b); });

      var list = show === 'done' ? done : show === 'gap' ? gap : none;

      el.innerHTML =
        '<div class="of-bar">' +
          '<button type="button" class="btn btn--fill" id="poaIn">위임장접수내역 CSV 올리기</button>' +
          '<span class="of-sp"></span>' +
          '<a class="btn" href="https://kingjin77-rgb.github.io/jl-demo/" target="_blank" rel="noopener">단지 플랫폼 데모 ↗</a>' +
        '</div>' +
        ui.stats([
          { k: '위임장 접수', v: done.length.toLocaleString(), s: pct + '%', tone: 'ok' },
          { k: '공동명의 서명', v: joint.toLocaleString() },
          { k: '아직 안 낸 세대', v: none.length.toLocaleString(), tone: none.length ? 'warn' : '' },
          { k: '위임장만 있고 등기 서류 없음', v: gap.length.toLocaleString(), tone: gap.length ? 'warn' : '' }
        ]) +
        '<h3 class="of-h3">동별 접수율</h3>' +
        '<div class="of-dongs">' + dongs.map(function (d) {
          var x = byDong[d], p = Math.round(x.y / x.n * 100);
          return '<div class="of-dong"><span class="d">' + esc(d) + '동</span>' +
            '<span class="b"><i style="height:' + p + '%"></i></span>' +
            '<span class="p">' + p + '%</span><span class="n">' + x.y + '/' + x.n + '</span></div>';
        }).join('') + '</div>' +
        '<div class="of-chips">' +
          [['none', '아직 안 낸 세대', none.length], ['gap', '등기 서류 없음', gap.length], ['done', '낸 세대', done.length]]
            .map(function (t) {
              return '<button type="button" data-ps="' + t[0] + '" class="' + (show === t[0] ? 'on' : '') + '">' + t[1] + ' <b>' + t[2] + '</b></button>';
            }).join('') +
          '<span class="of-sp"></span>' +
          '<button type="button" class="btn sm" id="poaCsv">CSV</button>' +
          (show !== 'done' && list.length ? '<button type="button" class="btn sm btn--fill" id="poaSms">이 세대에 문자 보내기</button>' : '') +
        '</div>' +
        ui.table([
          { t: '동', k: 'dong', cls: 'num' }, { t: '호', k: 'ho', cls: 'num' },
          { t: '명의자', f: function (u) { return esc(u.owners.map(function (o) { return o.name; }).join(' · ')); } },
          { t: '휴대폰', f: function (u) { return esc((u.owners[0] || {}).phone) || '<span class="of-pill warn">없음</span>'; } },
          { t: '위임장', f: function (u) {
              return u.poa ? '<span class="of-pill ok">' + (u.poa.joint ? '공동명의' : '단독') + '</span> <span class="of-num">' + esc(u.poa.at) + '</span>'
                           : '<span class="of-pill mute">미제출</span>';
          } },
          { t: '등기 단계', f: function (u) { return esc(u.step); } }
        ], list, { empty: show === 'gap' ? '위임장을 낸 세대는 모두 등기 서류도 들어왔습니다.' : '해당 세대가 없습니다.' });

      $('poaIn').addEventListener('click', function () { mergeCsv(cx); });
      el.querySelectorAll('[data-ps]').forEach(function (b) {
        b.addEventListener('click', function () { show = b.dataset.ps; ui.go('poa'); });
      });
      $('poaCsv').addEventListener('click', function () {
        var rows = [['동', '호', '명의자', '휴대폰', '위임장', '접수일시', '등기단계']];
        list.forEach(function (u) {
          rows.push([u.dong, u.ho, u.owners.map(function (o) { return o.name; }).join(' '), (u.owners[0] || {}).phone,
            u.poa ? (u.poa.joint ? '공동명의' : '단독') : '미제출', u.poa ? u.poa.at : '', u.step]);
        });
        JL.csv(rows, cx + '_위임장_' + JL.today() + '.csv');
      });
      if ($('poaSms')) $('poaSms').addEventListener('click', function () {
        JL.smsTarget = {
          complex: cx,
          label: show === 'gap' ? '위임장만 있고 등기 서류 없는 ' + list.length + '세대' : '위임장 미제출 ' + list.length + '세대',
          keys: list.map(function (u) { return JL.unitKey(u.dong, u.ho); })
        };
        ui.go('sms');
      });
    }
  });

})(window);
