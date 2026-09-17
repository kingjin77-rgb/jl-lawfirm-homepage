/* 한눈에 보기 — 옛 시스템의 접속 통계 자리
 *
 * 접속 통계는 서버 기록이 있어야 나온다. 서버가 붙기 전까지는
 * 직원이 매일 여는 화면에서 정말 필요한 숫자를 먼저 보여준다.
 * 어느 단지가 어디서 멈춰 있는지, 무엇이 밀려 있는지.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc;

  function summarize(name) {
    var units = JL.units(name), s = {
      total: units.length, done: 0, poa: 0, lack: 0, refund: 0, unpaid: 0, back: 0, steps: {}
    };
    units.forEach(function (u) {
      s.steps[u.step] = (s.steps[u.step] || 0) + 1;
      if (JL.STEPS.indexOf(u.step) >= JL.DONE_FROM) s.done++;
      if (u.poa) s.poa++;
      if (u.lack) s.lack++;
      if (u.refund.account && u.refund.status !== '완료') s.refund++;
      if (u.cost.total && !u.cost.paid) s.unpaid++;
      if (u.cost.diff > 0) s.back++;
    });
    return s;
  }

  JL.mod('home', {
    title: '한눈에 보기',
    icon: '◎',
    lead: '단지마다 어디서 멈춰 있고 무엇이 밀려 있는지 먼저 봅니다.',

    render: function (el) {
      var names = JL.complexNames();
      if (!names.length) {
        el.innerHTML =
          '<div class="of-blank"><h3>아직 자료가 없습니다</h3>' +
          '<p>등기진행에서 쓰시던 엑셀을 올리면 단지와 세대가 한 번에 만들어집니다.<br>' +
          '이어서 하시려면 위쪽 「저장본 열기」로 지난번 파일을 여십시오.</p>' +
          '<div class="of-acts"><button type="button" class="btn btn--fill" data-go="progress">등기 엑셀 불러오기</button>' +
          '<button type="button" class="btn" data-go="settings">단지 직접 추가</button></div></div>';
        return;
      }

      var all = { total: 0, done: 0, poa: 0, lack: 0, refund: 0, unpaid: 0 };
      var rows = names.map(function (n) {
        var s = summarize(n);
        Object.keys(all).forEach(function (k) { all[k] += s[k]; });
        return { name: n, s: s };
      });

      var html = ui.stats([
        { k: '관리 중인 세대', v: all.total.toLocaleString(), s: '단지 ' + names.length + '개' },
        { k: '등기 완료', v: all.done.toLocaleString(),
          s: all.total ? Math.round(all.done / all.total * 100) + '%' : '' , tone: 'ok' },
        { k: '미비서류 남은 세대', v: all.lack.toLocaleString(), tone: all.lack ? 'warn' : '' },
        { k: '등기비 미입금', v: all.unpaid.toLocaleString(), tone: all.unpaid ? 'warn' : '' },
        { k: '채권환불 처리 대기', v: all.refund.toLocaleString(), tone: all.refund ? 'warn' : '' }
      ]);

      html += '<h3 class="of-h3">단지별 진행</h3>';
      html += '<div class="of-cards">' + rows.map(function (r) {
        var s = r.s, pct = s.total ? Math.round(s.done / s.total * 100) : 0;
        var bars = [JL.NOT_YET].concat(JL.STEPS).map(function (st, i) {
          var n = s.steps[st] || 0;
          if (!n) return '';
          var w = n / s.total * 100;
          return '<i style="width:' + w + '%" class="s' + i + '" title="' + esc(st) + ' ' + n + '세대"></i>';
        }).join('');
        var legend = [JL.NOT_YET].concat(JL.STEPS).map(function (st, i) {
          var n = s.steps[st] || 0;
          return n ? '<span><i class="s' + i + '"></i>' + esc(st) + ' <b>' + n + '</b></span>' : '';
        }).join('');
        return '<article class="of-cx">' +
          '<header><h4>' + esc(r.name) + '</h4><span class="pct">' + pct + '% 완료</span></header>' +
          '<div class="of-stack">' + bars + '</div>' +
          '<div class="of-legend">' + legend + '</div>' +
          '<dl class="of-mini">' +
            '<div><dt>세대</dt><dd>' + s.total.toLocaleString() + '</dd></div>' +
            '<div><dt>위임장</dt><dd>' + (s.poa ? s.poa.toLocaleString() : '—') + '</dd></div>' +
            '<div><dt>미비서류</dt><dd class="' + (s.lack ? 'warn' : '') + '">' + s.lack + '</dd></div>' +
            '<div><dt>미입금</dt><dd class="' + (s.unpaid ? 'warn' : '') + '">' + s.unpaid + '</dd></div>' +
          '</dl>' +
          '<div class="of-acts sm">' +
            '<button type="button" class="btn" data-open="' + esc(r.name) + '">등기진행 열기</button>' +
          '</div>' +
          '</article>';
      }).join('') + '</div>';

      html += '<div class="of-callout">' +
        '<b>접속 통계는 서버가 붙은 뒤에 나옵니다.</b> ' +
        '고객이 조회 화면을 몇 번 열었는지, 어느 단지에서 조회가 많이 실패하는지는 서버 기록에서 셉니다.' +
        '</div>';

      el.innerHTML = html;
      el.querySelectorAll('[data-open]').forEach(function (b) {
        b.addEventListener('click', function () {
          JL.$('ofComplex').value = b.dataset.open;
          ui.go('progress');
        });
      });
    }
  });

})(window);
