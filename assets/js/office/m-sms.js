/* 문자 발송 — 고객 자동 안내
 *
 * 옛 시스템은 단지를 골라 {회원이름} 을 넣은 문자를 한 번에 보냈다.
 * 단문 90바이트, 장문 2,000바이트. 발신번호는 통신사에 미리 등록한 번호만.
 * 그 규칙을 그대로 따른다.
 *
 * 실제 발송은 문자 업체와 계약한 서버가 한다. 브라우저에서 직접 보낼 수 없다.
 * 서버가 붙기 전에는 받는 사람과 본문을 다 채운 발송 파일을 만들어
 * 문자 업체 관리 화면에 올린다. 업체들이 받는 엑셀 양식이 대개 같다.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc, $ = JL.$;

  var TAGS = [
    ['{이름}', '명의자 이름'], ['{단지}', '단지명'], ['{동}', '동'], ['{호}', '호'],
    ['{단계}', '지금 진행 단계'], ['{등기비용}', '등기비용 합계'], ['{차액}', '돌려드릴·더 내실 금액'],
    ['{미비서류}', '빠진 서류'], ['{조회링크}', '우리 세대 조회 주소']
  ];

  var PRESETS = [
    { t: '서류 보완 요청', b: '[법무법인 제이엘] {이름}님, {단지} {동}동 {호}호 등기 서류 중 {미비서류}이(가) 빠져 있습니다. 보내주시면 바로 진행하겠습니다. 문의 1899-4252' },
    { t: '등기비용 안내', b: '[법무법인 제이엘] {이름}님, {단지} {동}동 {호}호 등기비용은 {등기비용}원입니다. 항목별 명세는 아래에서 확인하실 수 있습니다.\n{조회링크}' },
    { t: '등기 완료', b: '[법무법인 제이엘] {이름}님, {단지} {동}동 {호}호 소유권이전등기가 끝났습니다. 권리증은 알려주신 주소로 보내드립니다.' },
    { t: '위임장 제출 요청', b: '[법무법인 제이엘] {단지} {동}동 {호}호 위임장이 아직 접수되지 않았습니다. 입주예정자협의회 플랫폼에서 제출해 주십시오.' },
    { t: '진행 상황 안내', b: '[법무법인 제이엘] {이름}님, {단지} {동}동 {호}호 등기는 현재 「{단계}」 단계입니다.\n{조회링크}' }
  ];

  var state = { body: '', target: 'all', keys: null, label: '' };

  /** 한글 2바이트, 영문·숫자·기호 1바이트. 통신사 계산과 같다. */
  function bytes(s) {
    var n = 0;
    for (var i = 0; i < s.length; i++) n += s.charCodeAt(i) > 127 ? 2 : 1;
    return n;
  }

  function fill(body, cx, u) {
    var o = u.owners[0] || {};
    var d = u.cost.diff;
    var base = location.origin + location.pathname.replace(/admin\/[^/]*$/, '');
    var map = {
      '{이름}': o.name || '고객', '{단지}': cx, '{동}': u.dong, '{호}': u.ho,
      '{단계}': u.step, '{등기비용}': JL.won(u.cost.total),
      '{차액}': d ? JL.won(Math.abs(d)) + '원' + (d > 0 ? ' 환급' : ' 추가납부') : '없음',
      '{미비서류}': u.lack || '',
      '{조회링크}': base + 'tracking.html?c=' + encodeURIComponent(cx)
    };
    return body.replace(/\{[^}]+\}/g, function (t) { return map[t] != null ? map[t] : t; });
  }

  function targets(cx) {
    var units = JL.units(cx);
    if (state.keys) {
      var set = {};
      state.keys.forEach(function (k) { set[k] = 1; });
      return units.filter(function (u) { return set[JL.unitKey(u.dong, u.ho)]; });
    }
    return units.filter(function (u) {
      switch (state.target) {
        case 'lack':   return !!u.lack;
        case 'unpaid': return u.cost.total && !u.cost.paid;
        case 'done':   return JL.STEPS.indexOf(u.step) >= JL.DONE_FROM;
        case 'nopoa':  return !u.poa;
        default:       return state.target.indexOf('step:') === 0 ? u.step === state.target.slice(5) : true;
      }
    });
  }

  JL.mod('sms', {
    title: '문자 발송',
    icon: '✉',
    lead: '고른 세대마다 이름과 동호를 채워 문자를 만듭니다.',

    render: function (el, cx) {
      if (ui.needComplex(el, cx)) return;

      // 설문·위임장 화면에서 대상을 넘겨받았으면 그걸 쓴다
      if (JL.smsTarget && JL.smsTarget.complex === cx) {
        state.keys = JL.smsTarget.keys; state.label = JL.smsTarget.label;
        JL.smsTarget = null;
      } else if (JL.smsTarget) {
        JL.smsTarget = null;
      }

      var list = targets(cx);
      // {미비서류} 를 쓴 문구는 빠진 서류가 없는 세대에 보내면 말이 안 된다.
      // "서류 중 이(가) 빠져 있습니다" 같은 문자가 나가지 않게 그 세대를 뺀다.
      var needLack = state.body.indexOf('{미비서류}') >= 0;
      var noLack = needLack ? list.filter(function (u) { return !u.lack; }).length : 0;
      var reach = list.filter(function (u) {
        if (needLack && !u.lack) return false;
        return u.owners.some(function (o) { return o.phone; });
      });
      var b = bytes(state.body), kind = b <= 90 ? '단문' : '장문', over = b > 2000;
      var sample = reach[0] ||
        (needLack ? list.filter(function (u) { return u.lack; })[0] : null) || list[0];
      var st = JL.db.settings;

      el.innerHTML =
        '<div class="of-sms">' +
        '<section class="of-panel">' +
          '<h3 class="of-h3">받는 세대</h3>' +
          (state.keys
            ? '<div class="of-callout"><b>' + esc(state.label) + '</b> 을 넘겨받았습니다. ' +
                '<button type="button" class="btn sm" id="smClearT">조건으로 다시 고르기</button></div>'
            : '<label class="of-fld"><span>조건</span><select id="smT">' +
                [['all', '단지 전체'], ['lack', '미비서류 남은 세대'], ['unpaid', '등기비 미입금'],
                 ['done', '등기 끝난 세대'], ['nopoa', '위임장 미제출']].concat(
                  [JL.NOT_YET].concat(JL.STEPS).map(function (s) { return ['step:' + s, '단계 · ' + s]; })
                ).map(function (o) {
                  return '<option value="' + o[0] + '"' + (state.target === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
                }).join('') +
              '</select></label>') +
          ui.stats([
            { k: '고른 세대', v: list.length.toLocaleString() },
            { k: '보낼 수 있음', v: reach.length.toLocaleString(), s: '휴대폰 있음', tone: 'ok' },
            { k: '보낼 수 없음', v: (list.length - reach.length).toLocaleString(),
              s: noLack ? '번호 없음·미비서류 없음' : '휴대폰 없음', tone: list.length - reach.length ? 'warn' : '' }
          ]) +
          (noLack
            ? '<div class="of-callout warn"><b>빠진 서류가 없는 ' + noLack.toLocaleString() + '세대는 뺍니다.</b> ' +
              '문구에 {미비서류} 가 들어 있어 그 세대에 보내면 문장이 성립하지 않습니다. ' +
              '받는 세대 조건을 「미비서류 남은 세대」로 고르면 딱 맞습니다.</div>' : '') +
          (list.length - reach.length - noLack > 0
            ? '<p class="of-p">번호가 없는 세대는 인적사항에서 연락처 파일을 합치면 채워집니다. <button type="button" class="of-link" data-go="people">인적사항으로 →</button></p>' : '') +

          '<h3 class="of-h3">문구</h3>' +
          '<div class="of-chips">' + PRESETS.map(function (p, i) {
            return '<button type="button" data-preset="' + i + '">' + esc(p.t) + '</button>';
          }).join('') + '</div>' +
          '<textarea id="smBody" class="of-ta" rows="7" placeholder="[법무법인 제이엘] {이름}님, ...">' + esc(state.body) + '</textarea>' +
          '<div class="of-count ' + (over ? 'err' : '') + '"><b>' + b.toLocaleString() + '</b> / ' +
            (kind === '단문' ? '90바이트 · 단문' : '2,000바이트 · 장문') + (over ? ' · 너무 깁니다' : '') + '</div>' +
          '<div class="of-tags">' + TAGS.map(function (t) {
            return '<button type="button" data-tag="' + t[0] + '" title="' + t[1] + '">' + t[0] + '</button>';
          }).join('') + '</div>' +
        '</section>' +

        '<section class="of-panel of-phone-wrap">' +
          '<h3 class="of-h3">받는 화면 미리보기</h3>' +
          '<div class="of-phone"><div class="of-phone__top">' + esc(st.sender || '발신번호 미등록') + '</div>' +
            '<div class="of-bubble">' + (state.body && sample ? esc(fill(state.body, cx, sample)).replace(/\n/g, '<br>') : '<span class="of-mute">문구를 적으면 첫 세대 기준으로 채워 보여 드립니다.</span>') + '</div>' +
          '</div>' +
          (sample ? '<p class="of-p">' + esc(sample.dong) + '동 ' + esc(sample.ho) + '호 기준</p>' : '') +
          '<div class="of-acts col">' +
            '<button type="button" class="btn btn--fill" id="smMake"' + (reach.length && state.body && !over ? '' : ' disabled') + '>' +
              '발송 파일 만들기 · ' + reach.length.toLocaleString() + '건</button>' +
            '<p class="of-note">문자 업체 관리 화면에 이 파일을 올려 보내십시오. 서버가 붙으면 이 단추가 바로 보내기로 바뀝니다.</p>' +
          '</div>' +
        '</section>' +
        '</div>' +

        '<h3 class="of-h3">발송 기록</h3>' +
        ui.table([
          { t: '만든 때', cls: 'num', f: function (r) { return esc(r.at); } },
          { t: '단지', f: function (r) { return esc(r.complex); } },
          { t: '대상', f: function (r) { return esc(r.label); } },
          { t: '건수', cls: 'num', f: function (r) { return r.n.toLocaleString(); } },
          { t: '구분', f: function (r) { return esc(r.kind); } },
          { t: '문구', f: function (r) { return esc(r.body.slice(0, 40)) + (r.body.length > 40 ? '…' : ''); } },
          { t: '상태', f: function (r) { return '<span class="of-pill ' + (r.status === '발송 완료' ? 'ok' : 'warn') + '">' + esc(r.status) + '</span>'; } }
        ], JL.db.sms.log, { empty: '아직 발송 기록이 없습니다.', max: 50 });

      if ($('smT')) $('smT').addEventListener('change', function () { state.target = this.value; ui.go('sms'); });
      if ($('smClearT')) $('smClearT').addEventListener('click', function () { state.keys = null; state.label = ''; ui.go('sms'); });

      var ta = $('smBody');
      var redraw = function () {
        var pos = ta.selectionStart;
        state.body = ta.value;
        ui.go('sms');
        var n = $('smBody'); n.focus(); n.setSelectionRange(pos, pos);
      };
      var t = null;
      ta.addEventListener('input', function () { clearTimeout(t); t = setTimeout(redraw, 350); });

      el.querySelectorAll('[data-preset]').forEach(function (b) {
        b.addEventListener('click', function () { state.body = PRESETS[b.dataset.preset].b; ui.go('sms'); });
      });
      el.querySelectorAll('[data-tag]').forEach(function (b) {
        b.addEventListener('click', function () {
          var p = ta.selectionStart || ta.value.length;
          state.body = ta.value.slice(0, p) + b.dataset.tag + ta.value.slice(p);
          ui.go('sms');
          var n = $('smBody'); n.focus(); n.setSelectionRange(p + b.dataset.tag.length, p + b.dataset.tag.length);
        });
      });

      if ($('smMake')) $('smMake').addEventListener('click', function () {
        if (!st.sender) {
          return ui.toast('발신번호가 없습니다. 기본 정보에서 통신사에 등록한 발신번호를 넣으십시오.', 'err');
        }
        var rows = [['수신번호', '이름', '동', '호', '메시지']];
        reach.forEach(function (u) {
          var msg = fill(state.body, cx, u);
          u.owners.forEach(function (o) {
            if (o.phone) rows.push([o.phone, o.name, u.dong, u.ho, msg]);
          });
        });
        JL.csv(rows, '문자발송_' + cx.replace(/\s+/g, '') + '_' + JL.today() + '.csv');
        JL.db.sms.log.unshift({
          at: JL.now(), complex: cx, label: state.label || (($('smT') || {}).selectedOptions || [{}])[0].text || '',
          n: rows.length - 1, kind: kind, body: state.body, status: '파일 생성'
        });
        JL.touch(); ui.go('sms');
        ui.toast('발송 파일 ' + (rows.length - 1) + '건을 만들었습니다. 공동명의는 두 분 모두에게 갑니다.', 'ok');
      });
    }
  });

})(window);
