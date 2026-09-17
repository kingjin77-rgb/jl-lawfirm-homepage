/* 등기접수 관리 — 들어온 등기 문의와 신청
 *
 * 등기센터의 개별등기 온라인 접수와 채팅 문의가 여기로 모인다.
 * 서버가 붙기 전에는 메일로 받은 접수를 직원이 옮겨 적는다.
 * 수임이 정해지면 한 번에 등기진행의 세대로 넘긴다. 같은 것을 두 번 적지 않게.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc, $ = JL.$;

  var STATUS = ['새 접수', '연락함', '서류 안내함', '수임', '보류', '취소'];
  var KINDS = ['입주 소유권이전', '분양전환', '대지권', '근저당 설정·말소', '법인등기', '기타'];
  var filter = '';

  function edit(it) {
    var isNew = !it;
    it = it || { id: JL.uid(), complex: ui.complex() || '', dong: '', ho: '', name: '', phone: '',
                 kind: KINDS[0], status: STATUS[0], note: '', at: JL.now() };
    ui.dialog({
      title: isNew ? '접수 등록' : '접수 · ' + it.name,
      body:
        '<div class="of-form two">' +
          '<label class="of-fld"><span>성함</span><input id="inN" value="' + esc(it.name) + '"></label>' +
          '<label class="of-fld"><span>휴대폰</span><input id="inP" inputmode="tel" value="' + esc(it.phone) + '"></label>' +
        '</div>' +
        '<div class="of-form three">' +
          '<label class="of-fld"><span>아파트</span><input id="inC" list="inCxList" value="' + esc(it.complex) + '"></label>' +
          '<label class="of-fld"><span>동</span><input id="inD" inputmode="numeric" value="' + esc(it.dong) + '"></label>' +
          '<label class="of-fld"><span>호</span><input id="inH" inputmode="numeric" value="' + esc(it.ho) + '"></label>' +
        '</div>' +
        '<datalist id="inCxList">' + JL.complexNames().map(function (n) { return '<option value="' + esc(n) + '">'; }).join('') + '</datalist>' +
        '<div class="of-form two">' +
          '<label class="of-fld"><span>등기 종류</span><select id="inK">' +
            KINDS.map(function (k) { return '<option' + (k === it.kind ? ' selected' : '') + '>' + k + '</option>'; }).join('') +
          '</select></label>' +
          '<label class="of-fld"><span>상태</span><select id="inS">' +
            STATUS.map(function (k) { return '<option' + (k === it.status ? ' selected' : '') + '>' + k + '</option>'; }).join('') +
          '</select></label>' +
        '</div>' +
        '<label class="of-fld"><span>메모</span><textarea id="inM" rows="3">' + esc(it.note) + '</textarea></label>',
      ok: isNew ? '등록' : '반영',
      onOk: function () {
        var name = $('inN').value.trim();
        if (!name) return '성함을 입력해 주십시오.';
        var ph = JL.phone($('inP').value);
        if ($('inP').value.trim() && !ph) return '휴대폰 번호를 확인해 주십시오.';
        it.name = name; it.phone = ph;
        it.complex = $('inC').value.trim();
        it.dong = JL.digits($('inD').value); it.ho = JL.digits($('inH').value);
        it.kind = $('inK').value; it.status = $('inS').value;
        it.note = $('inM').value.trim();
        it.updatedAt = JL.now();
        if (isNew) JL.db.intake.unshift(it);
        JL.touch(); ui.go('intake');
        return true;
      }
    });
  }

  /** 수임한 접수를 등기진행 세대로 넘긴다. */
  function promote(it) {
    if (!it.complex || !it.dong || !it.ho) {
      return ui.toast('아파트·동·호가 모두 있어야 등기진행으로 넘길 수 있습니다. 먼저 수정하십시오.', 'err');
    }
    var c = JL.complex(it.complex), key = JL.unitKey(it.dong, it.ho), u = c.units[key];
    if (!u) {
      u = JL.newUnit(it.dong, it.ho);
      u.step = '서류수령';
      u.stepAt = JL.today();
      c.units[key] = u;
    }
    if (!u.owners.length) u.owners.push({ name: it.name, birth: '', phone: it.phone });
    else if (!u.owners[0].phone) u.owners[0].phone = it.phone;
    it.status = '수임';
    it.unit = key;
    JL.touch(); ui.drawComplexes(it.complex);
    ui.toast(it.complex + ' ' + it.dong + '동 ' + it.ho + '호를 등기진행에 올렸습니다. 생년월일은 인적사항에서 채우십시오.', 'ok');
    ui.go('intake');
  }

  JL.mod('intake', {
    title: '등기접수',
    icon: '✚',
    lead: '들어온 등기 문의와 신청을 처리하고, 수임하면 등기진행으로 넘깁니다.',
    badge: function () {
      return JL.db.intake.filter(function (i) { return i.status === '새 접수'; }).length;
    },

    render: function (el) {
      var all = JL.db.intake;
      var count = {};
      all.forEach(function (i) { count[i.status] = (count[i.status] || 0) + 1; });
      var list = all.filter(function (i) { return !filter || i.status === filter; });

      el.innerHTML =
        '<div class="of-callout">' +
          '<b>서버가 붙으면 등기센터 접수와 채팅 문의가 여기로 바로 들어옵니다.</b> ' +
          '그 전에는 메일로 받은 접수를 「접수 등록」으로 옮겨 적으십시오.' +
        '</div>' +
        '<div class="of-chips">' +
          '<button type="button" data-if="" class="' + (filter ? '' : 'on') + '">전체 <b>' + all.length + '</b></button>' +
          STATUS.map(function (s) {
            return count[s] ? '<button type="button" data-if="' + s + '" class="' + (filter === s ? 'on' : '') + '">' + s + ' <b>' + count[s] + '</b></button>' : '';
          }).join('') +
          '<span class="of-sp"></span>' +
          '<button type="button" class="btn sm" id="inCsv">CSV</button>' +
          '<button type="button" class="btn btn--fill" id="inNew">접수 등록</button>' +
        '</div>' +
        ui.table([
          { t: '접수', cls: 'num', f: function (i) { return esc(i.at.slice(5, 16)); } },
          { t: '상태', f: function (i) {
              var tone = { '새 접수': 'warn', '수임': 'ok', '취소': 'mute', '보류': 'mute' }[i.status] || '';
              return '<span class="of-pill ' + tone + '">' + esc(i.status) + '</span>';
          } },
          { t: '성함', f: function (i) { return '<b>' + esc(i.name) + '</b>'; } },
          { t: '휴대폰', cls: 'num', f: function (i) { return esc(i.phone); } },
          { t: '아파트 · 동호', f: function (i) {
              return esc(i.complex) + (i.dong ? ' <span class="of-num">' + esc(i.dong) + '-' + esc(i.ho) + '</span>' : '');
          } },
          { t: '종류', f: function (i) { return esc(i.kind); } },
          { t: '메모', f: function (i) { return esc((i.note || '').slice(0, 30)); } },
          { t: '', f: function (i) {
              return '<button type="button" class="btn sm" data-ie="' + i.id + '">열기</button> ' +
                (i.unit ? '<span class="of-pill ok">진행 중</span>'
                        : '<button type="button" class="btn sm btn--fill" data-ip="' + i.id + '">등기진행으로</button>');
          } }
        ], list, { empty: filter ? filter + ' 상태인 접수가 없습니다.' : '들어온 접수가 없습니다.' });

      var find = function (id) { return all.filter(function (x) { return x.id === id; })[0]; };
      el.querySelectorAll('[data-if]').forEach(function (b) {
        b.addEventListener('click', function () { filter = b.dataset.if; ui.go('intake'); });
      });
      $('inNew').addEventListener('click', function () { edit(null); });
      el.querySelectorAll('[data-ie]').forEach(function (b) {
        b.addEventListener('click', function () { edit(find(b.dataset.ie)); });
      });
      el.querySelectorAll('[data-ip]').forEach(function (b) {
        b.addEventListener('click', function () { promote(find(b.dataset.ip)); });
      });
      $('inCsv').addEventListener('click', function () {
        var rows = [['접수일시', '상태', '성함', '휴대폰', '아파트', '동', '호', '종류', '메모']];
        list.forEach(function (i) { rows.push([i.at, i.status, i.name, i.phone, i.complex, i.dong, i.ho, i.kind, i.note]); });
        JL.csv(rows, '등기접수_' + JL.today() + '.csv');
      });
    }
  });

})(window);
