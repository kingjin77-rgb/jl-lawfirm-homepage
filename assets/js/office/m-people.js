/* 인적사항 관리 — 세대와 명의자, 연락처
 *
 * 등기 엑셀에는 휴대폰 칸이 있지만 세 단지 모두 비어 있었다.
 * 옛 시스템도 연락처는 인적사항에서 따로 받았다. 그래서 연락처만 따로 합치는 길을 둔다.
 * 동·호로 맞추므로 연락처 파일에 이름이 없어도 된다.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc, $ = JL.$;

  var q = '';

  function ownerCell(u) {
    if (!u.owners.length) return '<span class="of-mute">명의자 없음</span>';
    return u.owners.map(function (o, i) {
      return '<div class="of-owner">' + (i ? '<span class="of-pill mute">공동</span> ' : '') +
        '<b>' + esc(o.name) + '</b> <span class="of-num">' + esc(o.birth) + '</span></div>';
    }).join('');
  }

  function phoneCell(u) {
    var ph = u.owners.map(function (o) { return o.phone; }).filter(Boolean);
    return ph.length ? ph.map(function (p) { return '<div class="of-num">' + esc(p) + '</div>'; }).join('')
      : '<span class="of-pill warn">없음</span>';
  }

  function editUnit(cx, u) {
    var isNew = !u;
    u = u || JL.newUnit('', '');
    var o1 = u.owners[0] || {}, o2 = u.owners[1] || {};
    ui.dialog({
      title: isNew ? '세대 등록' : u.dong + '동 ' + u.ho + '호',
      body:
        '<div class="of-form two">' +
          '<label class="of-fld"><span>동</span><input id="uDong" inputmode="numeric" value="' + esc(u.dong) + '"' + (isNew ? '' : ' disabled') + '></label>' +
          '<label class="of-fld"><span>호</span><input id="uHo" inputmode="numeric" value="' + esc(u.ho) + '"' + (isNew ? '' : ' disabled') + '></label>' +
        '</div>' +
        '<h4 class="of-h4">명의자</h4>' +
        '<div class="of-form three">' +
          '<label class="of-fld"><span>성명</span><input id="o1n" value="' + esc(o1.name) + '"></label>' +
          '<label class="of-fld"><span>생년월일 6자리</span><input id="o1b" inputmode="numeric" maxlength="6" value="' + esc(o1.birth) + '"></label>' +
          '<label class="of-fld"><span>휴대폰</span><input id="o1p" inputmode="tel" value="' + esc(o1.phone) + '"></label>' +
        '</div>' +
        '<h4 class="of-h4">공동명의자 <span class="of-mute">없으면 비워 두십시오</span></h4>' +
        '<div class="of-form three">' +
          '<label class="of-fld"><span>성명</span><input id="o2n" value="' + esc(o2.name) + '"></label>' +
          '<label class="of-fld"><span>생년월일 6자리</span><input id="o2b" inputmode="numeric" maxlength="6" value="' + esc(o2.birth) + '"></label>' +
          '<label class="of-fld"><span>휴대폰</span><input id="o2p" inputmode="tel" value="' + esc(o2.phone) + '"></label>' +
        '</div>',
      ok: isNew ? '등록' : '반영',
      onOk: function () {
        var dong = JL.digits($('uDong').value), ho = JL.digits($('uHo').value);
        if (!dong || !ho) return '동과 호를 숫자로 입력해 주십시오.';
        var c = JL.complex(cx), key = JL.unitKey(dong, ho);
        if (isNew && c.units[key]) return dong + '동 ' + ho + '호는 이미 있습니다.';
        var n1 = $('o1n').value.trim();
        if (!n1) return '명의자 성명을 입력해 주십시오.';
        var b1 = JL.digits($('o1b').value);
        if (b1 && b1.length !== 6) return '생년월일은 6자리입니다.';
        // 번호를 잘못 넣으면 조용히 비우지 않고 알린다. 비우면 문자 대상에서 말없이 빠진다.
        var p1 = JL.phone($('o1p').value);
        if ($('o1p').value.trim() && !p1) return '명의자 휴대폰 번호를 확인해 주십시오.';
        var owners = [{ name: n1, birth: b1, phone: p1 }];
        var n2 = $('o2n').value.trim();
        if (n2) {
          var b2 = JL.digits($('o2b').value);
          if (b2 && b2.length !== 6) return '공동명의자 생년월일은 6자리입니다.';
          var p2 = JL.phone($('o2p').value);
          if ($('o2p').value.trim() && !p2) return '공동명의자 휴대폰 번호를 확인해 주십시오.';
          owners.push({ name: n2, birth: b2, phone: p2 });
        }
        if (isNew) { u.dong = dong; u.ho = ho; c.units[key] = u; }
        u.owners = owners;
        JL.touch(); ui.drawComplexes(cx); ui.go('people');
        ui.toast(dong + '동 ' + ho + '호를 ' + (isNew ? '등록' : '반영') + '했습니다.', 'ok');
        return true;
      }
    });
  }

  /** 연락처 합치기 — CSV 든 엑셀이든 동·호·휴대폰 칸만 있으면 된다. */
  function mergePhones(cx) {
    JL.pickFile('.csv,.xlsx,.xls', function (file) {
      var isCsv = /\.csv$/i.test(file.name);
      if (!isCsv && !JL.needXlsx()) return;
      var rowsP = isCsv
        ? JL.readText(file).then(JL.parseCsv)
        : file.arrayBuffer().then(function (ab) {
            var wb = XLSX.read(new Uint8Array(ab), { type: 'array' });
            return XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, defval: '' });
          });

      rowsP.then(function (rows) {
        var hi = -1, cD = -1, cH = -1, cP = [], cN = -1;
        for (var i = 0; i < Math.min(rows.length, 12) && hi < 0; i++) {
          var h = rows[i].map(function (c) { return String(c).trim(); });
          var d = h.indexOf('동'), o = h.findIndex(function (c) { return c === '호' || c === '호수'; });
          if (d >= 0 && o >= 0) {
            hi = i; cD = d; cH = o;
            h.forEach(function (c, k) { if (/휴대|핸드폰|연락처|전화/.test(c)) cP.push(k); });
            cN = h.findIndex(function (c) { return /성명|이름/.test(c); });
          }
        }
        if (hi < 0 || !cP.length) {
          return ui.toast('동·호·휴대폰 칸을 찾지 못했습니다. 첫 줄에 "동, 호, 휴대폰" 제목이 있어야 합니다.', 'err');
        }
        var c = JL.complex(cx), hit = 0, miss = 0, bad = 0;
        rows.slice(hi + 1).forEach(function (r) {
          var u = c.units[JL.unitKey(r[cD], r[cH])];
          if (!u) { if (JL.digits(r[cD])) miss++; return; }
          var phones = cP.map(function (k) { return JL.phone(r[k]); }).filter(Boolean);
          if (!phones.length) { bad++; return; }
          if (!u.owners.length) {
            u.owners.push({ name: cN >= 0 ? String(r[cN]).trim() : '', birth: '', phone: '' });
          }
          phones.forEach(function (p, k) {
            if (u.owners[k]) u.owners[k].phone = p;
          });
          hit++;
        });
        JL.touch(); ui.go('people');
        ui.toast('연락처 ' + hit + '세대를 합쳤습니다' +
          (miss ? ' · 명단에 없는 세대 ' + miss : '') + (bad ? ' · 번호를 알아볼 수 없는 줄 ' + bad : '') + '.', 'ok');
      }).catch(function () {
        ui.toast('파일을 읽지 못했습니다.', 'err');
      });
    });
  }

  JL.mod('people', {
    title: '인적사항',
    icon: '👤',
    lead: '세대마다 명의자와 연락처를 관리합니다. 문자 발송이 이 연락처로 나갑니다.',

    render: function (el, cx) {
      if (ui.needComplex(el, cx)) return;
      var units = JL.units(cx);
      var withPhone = units.filter(function (u) { return u.owners.some(function (o) { return o.phone; }); }).length;
      var joint = units.filter(function (u) { return u.owners.length > 1; }).length;

      /* 검색은 표만 다시 그린다. 입력칸까지 새로 그리면 한글 조합 중인 글자가 끊긴다. */
      function drawList() {
        var list = units.filter(function (u) {
          if (!q) return true;
          var hay = u.dong + ' ' + u.ho + ' ' + u.owners.map(function (o) { return o.name + ' ' + o.phone; }).join(' ');
          return hay.indexOf(q) >= 0;
        });
        var box = $('pList');
        box.innerHTML = ui.table([
          { t: '동', k: 'dong', cls: 'num' },
          { t: '호', k: 'ho', cls: 'num' },
          { t: '명의자 · 생년월일', f: ownerCell },
          { t: '휴대폰', f: phoneCell },
          { t: '진행 단계', f: function (u) { return esc(u.step); } },
          { t: '', f: function (u) {
              return '<button type="button" class="btn sm" data-edit="' + JL.unitKey(u.dong, u.ho) + '">수정</button>';
          } }
        ], list, { empty: q ? '찾는 세대가 없습니다.' : '세대가 없습니다. 등기진행에서 엑셀을 올리거나 세대를 등록하십시오.' });
        box.querySelectorAll('[data-edit]').forEach(function (b) {
          b.addEventListener('click', function () {
            editUnit(cx, JL.db.complexes[cx].units[b.dataset.edit]);
          });
        });
      }

      el.innerHTML =
        ui.stats([
          { k: '세대', v: units.length.toLocaleString() },
          { k: '공동명의', v: joint.toLocaleString(), s: units.length ? Math.round(joint / units.length * 100) + '%' : '' },
          { k: '연락처 있음', v: withPhone.toLocaleString(), tone: withPhone ? 'ok' : '' },
          { k: '연락처 없음', v: (units.length - withPhone).toLocaleString(), tone: units.length - withPhone ? 'warn' : '' }
        ]) +
        '<div class="of-bar">' +
          '<input type="search" id="pQ" class="of-search" placeholder="동·호·이름·번호로 찾기" value="' + esc(q) + '">' +
          '<span class="of-sp"></span>' +
          '<button type="button" class="btn" id="pPhones">연락처 파일 합치기</button>' +
          '<button type="button" class="btn" id="pCsv">CSV 내려받기</button>' +
          '<button type="button" class="btn btn--fill" id="pAdd">세대 등록</button>' +
        '</div>' +
        '<div id="pList"></div>';
      drawList();

      var pQ = $('pQ');
      pQ.addEventListener('input', function (e) {
        if (e.isComposing) return;          // 조합이 끝나면 compositionend 가 다시 부른다
        q = this.value.trim(); drawList();
      });
      pQ.addEventListener('compositionend', function () { q = this.value.trim(); drawList(); });
      $('pAdd').addEventListener('click', function () { editUnit(cx, null); });
      $('pPhones').addEventListener('click', function () { mergePhones(cx); });
      $('pCsv').addEventListener('click', function () {
        var rows = [['동', '호', '명의자', '생년월일', '휴대폰', '공동명의자', '생년월일', '휴대폰', '진행단계']];
        units.forEach(function (u) {
          var a = u.owners[0] || {}, b = u.owners[1] || {};
          rows.push([u.dong, u.ho, a.name, a.birth, a.phone, b.name, b.birth, b.phone, u.step]);
        });
        JL.csv(rows, cx + '_인적사항_' + JL.today() + '.csv');
        ui.toast('CSV 를 내려받았습니다. 개인정보가 들어 있으니 다 쓰면 지우십시오.', 'ok');
      });
    }
  });

})(window);
