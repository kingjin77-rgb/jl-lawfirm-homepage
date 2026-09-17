/* 등기진행 관리 — 옛 시스템의 네 하위 메뉴를 탭으로
 *
 *   진행 현황 · 등기비용 현황 · 권리증 수령주소 현황 · 채권환불 신청현황
 *
 * 엑셀은 쓰던 고정 양식 그대로 받는다.
 *   1~3행 병합 제목, 4행 실제 제목, 5행부터 세대.
 *   진행 단계는 여덟 개 열에 "완료" 로 표시된다. 마지막 완료 칸이 현재 단계다.
 *   동·호·성명이 모두 0 인 줄은 나중에 채우려고 만들어 둔 빈 줄이다. 건너뛴다.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc, $ = JL.$;

  var tab = 'flow';
  var q = '';
  var stepFilter = null;
  var costFilter = '';
  var sel = {};
  var undo = null;

  /* ── 엑셀 읽기 ─────────────────────────────── */

  function col(head, test) {
    for (var i = 0; i < head.length; i++) { if (test(head[i])) return i; }
    return -1;
  }

  function parseWorkbook(ab) {
    var wb = XLSX.read(new Uint8Array(ab), { type: 'array', cellDates: true });
    var aoa = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, blankrows: false, defval: '' });

    var hi = -1;
    for (var i = 0; i < Math.min(aoa.length, 12); i++) {
      var r = (aoa[i] || []).map(function (c) { return String(c).trim(); });
      if (r.indexOf('동') >= 0 && (r.indexOf('호') >= 0 || r.indexOf('호수') >= 0) &&
          r.some(function (c) { return c.indexOf('성명') >= 0; })) { hi = i; break; }
    }
    if (hi < 0) throw new Error('제목 줄을 찾지 못했습니다. 동·호·성명이 있는 줄이 필요합니다.');

    var h = aoa[hi].map(function (c) { return String(c).replace(/\s+/g, ''); });
    var C = {
      dong: col(h, function (x) { return x === '동'; }),
      ho: col(h, function (x) { return x === '호' || x === '호수'; }),
      name: col(h, function (x) { return x.indexOf('성명') >= 0; }),
      birth: col(h, function (x) { return x.indexOf('주민') >= 0 || x.indexOf('생년') >= 0; }),
      total: col(h, function (x) { return x.indexOf('등기비용합계') >= 0; }),
      paidAt: col(h, function (x) { return x === '입금일'; }),
      paid: col(h, function (x) { return x === '입금액'; }),
      diff: col(h, function (x) { return x === '차액'; }),
      lack: col(h, function (x) { return x.indexOf('미비서류') >= 0; }),
      sentAt: col(h, function (x) { return x === '발송일'; }),
      addr: col(h, function (x) { return x.indexOf('권리증') >= 0 && x.indexOf('주소') >= 0; }),
      bank: col(h, function (x) { return x === '은행'; }),
      account: col(h, function (x) { return x === '계좌'; })
    };
    // 등기비용내역서 — 고객에게 합계만 주면 "보수료가 얼마냐" 는 전화가 온다.
    // 엑셀 제목 그대로 항목별로 챙긴다. 합계 앞에 있는 칸들이다.
    var ITEMS = ['취득세', '이전채권', '설정채권', '인지대', '증지대', '경유증표',
                 '신탁말소', '제증명', '보수료', '부가세', '기타', '송달료', '감면수수료'];
    var itemCols = ITEMS.map(function (name) {
      return { k: name === '기타' ? '기타(교통비 등)' : name,
               c: col(h, function (x) { return x === name; }) };
    }).filter(function (it) { return it.c >= 0; });

    var phones = [];
    h.forEach(function (x, k) { if (/핸드폰|휴대폰/.test(x)) phones.push(k); });
    if (!phones.length) {
      // 제목이 2행에 병합돼 4행이 비어 있는 경우가 있다
      (aoa[hi - 2] || []).forEach(function (x, k) { if (/핸드폰|휴대폰/.test(String(x))) phones.push(k); });
    }

    var steps = JL.STEPS.map(function (s, k) {
      var key = s.replace(/\s+/g, '');
      return col(h, function (x) { return x === (k + 1) + '.' + key || x === key; });
    });
    if (steps.filter(function (c) { return c >= 0; }).length < 4) {
      throw new Error('진행 단계 열을 찾지 못했습니다. 제목에 "1.서류수령" 같은 칸이 있어야 합니다.');
    }
    // 공동명의 — 성명 바로 오른쪽이 두 번째 이름, 주민번호 바로 오른쪽이 두 번째 생년월일
    var cName2 = C.name + 1 !== C.birth ? C.name + 1 : -1;
    var cBirth2 = C.birth >= 0 ? C.birth + 1 : -1;

    var num = function (v) {
      var s = JL.clean(v);
      if (!s) return 0;
      var n = Number(JL.digits(s)) || 0;
      return String(s).trim().charAt(0) === '-' ? -n : n;
    };

    var out = [], skipped = 0, broken = 0;
    aoa.slice(hi + 1).forEach(function (row) {
      var dong = JL.digits(row[C.dong]), ho = JL.digits(row[C.ho]);
      var nm = JL.clean(row[C.name]);
      if (!dong || !ho || !nm || Number(dong) === 0 || Number(ho) === 0 || nm === '0') { skipped++; return; }
      if (/^#/.test(String(row[C.name]))) { broken++; return; }

      var last = -1;
      steps.forEach(function (c, k) { if (c >= 0 && String(row[c]).trim() === '완료') last = k; });

      var u = JL.newUnit(dong, ho);
      u.owners.push({ name: nm, birth: C.birth >= 0 ? JL.birth6(row[C.birth]) : '', phone: phones[0] != null ? JL.phone(row[phones[0]]) : '' });
      var n2 = cName2 >= 0 ? JL.clean(row[cName2]) : '';
      if (n2 && n2 !== '0') {
        u.owners.push({ name: n2, birth: cBirth2 >= 0 ? JL.birth6(row[cBirth2]) : '', phone: phones[1] != null ? JL.phone(row[phones[1]]) : '' });
      }
      u.step = last < 0 ? JL.NOT_YET : JL.STEPS[last];
      u.stepAt = JL.today();
      u.cost = {
        total: C.total >= 0 ? num(row[C.total]) : 0,
        paid: C.paid >= 0 ? num(row[C.paid]) : 0,
        diff: C.diff >= 0 ? num(row[C.diff]) : 0,
        paidAt: C.paidAt >= 0 ? JL.dateOnly(row[C.paidAt]) : '',
        // 0 원인 항목도 남긴다. 고객은 "감면 수수료 0원" 을 보고 안심한다.
        items: itemCols.map(function (it) { return { k: it.k, v: num(row[it.c]) }; })
      };
      var lack = C.lack >= 0 ? JL.clean(row[C.lack]) : '';
      u.lack = lack === '0' ? '' : lack;
      u.cert = {
        addr: C.addr >= 0 ? JL.clean(row[C.addr]) : '',
        sentAt: C.sentAt >= 0 ? JL.dateOnly(row[C.sentAt]) : ''
      };
      var acct = C.account >= 0 ? JL.clean(row[C.account]) : '';
      u.refund = {
        bank: C.bank >= 0 ? JL.clean(row[C.bank]) : '',
        account: acct === '0' ? '' : acct,
        status: ''
      };
      out.push(u);
    });
    return { units: out, skipped: skipped, broken: broken };
  }

  function importExcel(cx) {
    JL.pickFile('.xlsx,.xls', function (file) {
      var start = function (name) {
        file.arrayBuffer().then(parseWorkbook).then(function (res) {
          var c = JL.complex(name), added = 0, updated = 0, moved = 0;
          res.units.forEach(function (u) {
            var k = JL.unitKey(u.dong, u.ho), t = c.units[k];
            if (t) {
              if (t.step !== u.step) moved++;
              // 직원이 이 화면에서 손으로 넣은 것은 엑셀이 덮지 않는다
              u.memo = t.memo;
              u.poa = t.poa;
              u.refund.status = t.refund.status;
              u.owners.forEach(function (o, i) {
                if (!o.phone && t.owners[i]) o.phone = t.owners[i].phone;
              });
              updated++;
            } else {
              added++;
            }
            c.units[k] = u;
          });
          undo = null; sel = {};
          JL.touch(); ui.drawComplexes(name); $('ofComplex').value = name;
          ui.go('progress');
          ui.toast(name + ' · 새 세대 ' + added + ', 갱신 ' + updated +
            (moved ? ' (단계 바뀜 ' + moved + ')' : '') +
            (res.skipped ? ' · 빈 줄 ' + res.skipped + ' 건너뜀' : '') +
            (res.broken ? ' · 수식 오류 ' + res.broken : ''), 'ok');
        }).catch(function (e) {
          ui.toast(e.message || '엑셀을 읽지 못했습니다.', 'err');
        });
      };

      var guess = file.name.replace(/\.(xlsx|xls)$/i, '');
      ui.dialog({
        title: '어느 단지 자료입니까',
        body:
          '<label class="of-fld"><span>단지</span><select id="imCx">' +
            JL.complexNames().map(function (n) {
              return '<option' + (n === cx ? ' selected' : '') + '>' + esc(n) + '</option>';
            }).join('') +
            '<option value="__new">새 단지로 추가</option>' +
          '</select></label>' +
          '<label class="of-fld" id="imNewWrap"' + (JL.complexNames().length ? ' hidden' : '') + '><span>새 단지명</span>' +
            '<input id="imNew" value="' + esc(guess) + '"></label>' +
          '<p class="of-note">같은 단지를 다시 올리면 동·호로 맞춰 갱신합니다. 고객 안내·위임장·환불 처리 표시는 지킵니다.</p>',
        ok: '불러오기',
        onOk: function () {
          var v = $('imCx').value, name = v === '__new' || !v ? $('imNew').value.trim() : v;
          if (!name) return '단지명을 입력해 주십시오.';
          start(name);
          return true;
        }
      });
      if (!JL.complexNames().length) $('imCx').value = '__new';
      $('imCx').addEventListener('change', function () { $('imNewWrap').hidden = this.value !== '__new'; });
    });
  }

  /* ── 서버 업로드본 ─────────────────────────── */
  function exportServer(cx) {
    ui.dialog({
      title: '서버 업로드본 내보내기',
      body:
        '<p class="of-p">이름과 생년월일은 해시로 바꿔 담습니다. 연락처·주소·계좌는 담지 않습니다.</p>' +
        '<label class="of-fld"><span>확인용 문구 (서버 config.php 의 verify_salt)</span>' +
          '<input type="password" id="exSalt" autocomplete="off"></label>' +
        '<p class="of-note">서버 설정과 글자 하나까지 같아야 조회가 됩니다.</p>',
      ok: '내려받기',
      onOk: function () {
        var salt = $('exSalt').value;
        if (!salt) return '확인용 문구를 입력해 주십시오.';
        var units = JL.units(cx);
        Promise.all(units.map(function (u) {
          return Promise.all(u.owners.filter(function (o) { return o.name && o.birth; })
            .map(function (o) { return JL.sha256(salt + '|' + o.name + '|' + o.birth); }))
            .then(function (hs) {
              return {
                dong: u.dong, ho: u.ho, vhash: hs, step: u.step, at: u.stepAt, memo: u.memo,
                total: u.cost.total, paid: u.cost.paid, diff: u.cost.diff, paidAt: u.cost.paidAt,
                items: u.cost.items || [],
                lack: u.lack ? 1 : 0, certSent: u.cert.sentAt ? 1 : 0, poa: u.poa ? 1 : 0
              };
            });
        })).then(function (list) {
          JL.download(new Blob([JSON.stringify({
            complex: cx, steps: JL.STEPS, exportedAt: JL.today(), households: list
          })], { type: 'application/json' }), 'upload_' + cx.replace(/\s+/g, '_') + '_' + JL.today() + '.json');
          ui.toast('서버 업로드본을 내려받았습니다.', 'ok');
        });
        return true;
      }
    });
  }

  /* ── 탭: 진행 현황 ─────────────────────────── */
  function renderFlow(el, cx, units) {
    var count = {};
    units.forEach(function (u) { count[u.step] = (count[u.step] || 0) + 1; });
    var list = units.filter(function (u) {
      if (stepFilter && u.step !== stepFilter) return false;
      return match(u);
    });
    var c = JL.db.complexes[cx];

    var chips = '<div class="of-chips"><button type="button" data-step="" class="' + (stepFilter ? '' : 'on') + '">전체 <b>' + units.length + '</b></button>' +
      [JL.NOT_YET].concat(JL.STEPS).map(function (s) {
        return count[s] ? '<button type="button" data-step="' + esc(s) + '" class="' + (stepFilter === s ? 'on' : '') + '">' +
          esc(s) + ' <b>' + count[s] + '</b></button>' : '';
      }).join('') + '</div>';

    el.innerHTML = chips + '<div id="pgBulk"></div>' + ui.table([
      { t: '선택', f: function (u) {
          var k = JL.unitKey(u.dong, u.ho);
          return '<input type="checkbox" class="of-chk" data-sel="' + k + '"' + (sel[k] ? ' checked' : '') + '>';
      } },
      { t: '동', k: 'dong', cls: 'num' },
      { t: '호', k: 'ho', cls: 'num' },
      { t: '명의자', f: function (u) {
          return u.owners.map(function (o, i) {
            return (i ? '<br><span class="of-mute">' : '<b>') + esc(o.name) + (i ? '</span>' : '</b>');
          }).join('');
      } },
      { t: '진행 단계', f: function (u) {
          var k = JL.unitKey(u.dong, u.ho);
          return '<select class="of-sel" data-step-of="' + k + '">' +
            [JL.NOT_YET].concat(JL.STEPS).map(function (s) {
              return '<option' + (s === u.step ? ' selected' : '') + '>' + esc(s) + '</option>';
            }).join('') + '</select>';
      } },
      { t: '위임장', f: function (u) {
          return u.poa ? '<span class="of-pill ok">제출</span>' : '<span class="of-pill mute">—</span>';
      } },
      { t: '미비서류', f: function (u) {
          return u.lack ? '<span class="of-pill warn" title="' + esc(u.lack) + '">' + esc(u.lack.slice(0, 12)) + '</span>' : '';
      } },
      { t: '고객에게 보이는 안내', f: function (u) {
          return '<input class="of-inline" data-memo="' + JL.unitKey(u.dong, u.ho) + '" value="' + esc(u.memo) +
            '" placeholder="예: 주민등록등본 다시 보내주십시오">';
      } }
    ], list, { empty: q || stepFilter ? '조건에 맞는 세대가 없습니다.' : '세대가 없습니다. 위쪽 「등기 엑셀 불러오기」를 누르십시오.' });

    /* 일괄 막대 — 체크할 때마다 표를 다시 그리면 수백 줄이 깜빡이고 보던 자리를 잃는다.
       막대만 제자리에서 갈아 끼운다. */
    function drawBulk() {
      var nSel = Object.keys(sel).filter(function (k) { return sel[k]; }).length;
      var box = $('pgBulk');
      if (!nSel && !undo) {
        box.innerHTML = list.length
          ? '<div class="of-bulk quiet"><button type="button" class="btn sm" id="bAll">보이는 ' + list.length.toLocaleString() + '세대 모두 선택</button>' +
            '<span class="of-p" style="margin:0">체크하면 여러 세대의 단계를 한 번에 바꿀 수 있습니다.</span></div>'
          : '';
      } else {
        box.innerHTML = '<div class="of-bulk">' +
          (nSel ? '<b>' + nSel + '세대 선택</b>' +
            '<select id="bStep">' + [JL.NOT_YET].concat(JL.STEPS).map(function (s) { return '<option>' + esc(s) + '</option>'; }).join('') + '</select>' +
            '<button type="button" class="btn btn--fill sm" id="bApply">선택한 세대 단계 바꾸기</button>' +
            '<button type="button" class="btn sm" id="bClear">선택 풀기</button>'
            : '<button type="button" class="btn sm" id="bAll">보이는 ' + list.length.toLocaleString() + '세대 모두 선택</button>') +
          '<span class="of-sp"></span>' +
          (undo ? '<button type="button" class="btn sm" id="bUndo">되돌리기 · ' + esc(undo.label) + '</button>' : '') +
          '</div>';
      }
      if ($('bAll')) $('bAll').addEventListener('click', function () {
        list.forEach(function (u) { sel[JL.unitKey(u.dong, u.ho)] = true; });
        el.querySelectorAll('[data-sel]').forEach(function (b) { b.checked = true; });
        drawBulk();
      });
      if ($('bClear')) $('bClear').addEventListener('click', function () {
        sel = {};
        el.querySelectorAll('[data-sel]').forEach(function (b) { b.checked = false; });
        drawBulk();
      });
      if ($('bApply')) $('bApply').addEventListener('click', function () {
        var to = $('bStep').value, snap = [];
        Object.keys(sel).forEach(function (k) {
          if (!sel[k] || !c.units[k]) return;
          var u = c.units[k];
          snap.push({ k: k, step: u.step, at: u.stepAt });
          u.step = to; u.stepAt = JL.today();
        });
        undo = { label: snap.length + '세대 → ' + to, snap: snap };
        sel = {};
        JL.touch(); ui.go('progress');
        ui.toast(snap.length + '세대를 "' + to + '" 로 바꿨습니다. 잘못 눌렀으면 되돌리기를 누르십시오.', 'ok');
      });
      if ($('bUndo')) $('bUndo').addEventListener('click', function () {
        undo.snap.forEach(function (s) {
          var u = c.units[s.k];
          if (u) { u.step = s.step; u.stepAt = s.at; }
        });
        var label = undo.label;
        undo = null;
        JL.touch(); ui.go('progress');
        ui.toast('되돌렸습니다 · ' + label, 'ok');
      });
    }
    drawBulk();

    el.querySelectorAll('[data-step]').forEach(function (b) {
      b.addEventListener('click', function () { stepFilter = b.dataset.step || null; sel = {}; ui.go('progress'); });
    });
    el.querySelectorAll('[data-sel]').forEach(function (b) {
      b.addEventListener('change', function () { sel[b.dataset.sel] = b.checked; drawBulk(); });
    });
    el.querySelectorAll('[data-step-of]').forEach(function (s) {
      s.addEventListener('change', function () {
        var u = c.units[s.dataset.stepOf];
        undo = { label: u.dong + '동 ' + u.ho + '호', snap: [{ k: s.dataset.stepOf, step: u.step, at: u.stepAt }] };
        u.step = s.value; u.stepAt = JL.today();
        JL.touch(); ui.go('progress');
      });
    });
    el.querySelectorAll('[data-memo]').forEach(function (i) {
      i.addEventListener('change', function () {
        c.units[i.dataset.memo].memo = i.value.trim();
        JL.touch();
        ui.toast('고객 안내를 반영했습니다.', 'ok');
      });
    });
  }

  /* ── 탭: 등기비용 ──────────────────────────── */
  function renderCost(el, cx, units) {
    var sum = { total: 0, paid: 0, unpaid: 0, back: 0, more: 0, backAmt: 0, moreAmt: 0 };
    units.forEach(function (u) {
      sum.total += u.cost.total; sum.paid += u.cost.paid;
      if (u.cost.total && !u.cost.paid) sum.unpaid++;
      if (u.cost.diff > 0) { sum.back++; sum.backAmt += u.cost.diff; }
      if (u.cost.diff < 0 && u.cost.paid) { sum.more++; sum.moreAmt += -u.cost.diff; }
    });
    var list = units.filter(function (u) {
      if (costFilter === 'unpaid' && !(u.cost.total && !u.cost.paid)) return false;
      if (costFilter === 'back' && !(u.cost.diff > 0)) return false;
      if (costFilter === 'more' && !(u.cost.diff < 0 && u.cost.paid)) return false;
      return match(u);
    });

    el.innerHTML =
      ui.stats([
        { k: '등기비용 합계', v: JL.won(sum.total) + '원' },
        { k: '입금 합계', v: JL.won(sum.paid) + '원' },
        { k: '미입금 세대', v: sum.unpaid.toLocaleString(), tone: sum.unpaid ? 'warn' : '' },
        { k: '돌려드릴 세대', v: sum.back.toLocaleString(), s: JL.won(sum.backAmt) + '원', tone: 'ok' },
        { k: '더 받을 세대', v: sum.more.toLocaleString(), s: JL.won(sum.moreAmt) + '원', tone: sum.more ? 'warn' : '' }
      ]) +
      '<div class="of-chips">' +
        [['', '전체'], ['unpaid', '미입금'], ['back', '돌려드릴 금액'], ['more', '더 받을 금액']].map(function (f) {
          return '<button type="button" data-cf="' + f[0] + '" class="' + (costFilter === f[0] ? 'on' : '') + '">' + f[1] + '</button>';
        }).join('') +
        '<span class="of-sp"></span><button type="button" class="btn sm" id="cCsv">CSV 내려받기</button>' +
      '</div>' +
      ui.table([
        { t: '동', k: 'dong', cls: 'num' },
        { t: '호', k: 'ho', cls: 'num' },
        { t: '명의자', f: function (u) { return esc((u.owners[0] || {}).name); } },
        { t: '등기비용', cls: 'num', f: function (u) { return JL.won(u.cost.total); } },
        { t: '입금일', cls: 'num', f: function (u) { return esc(u.cost.paidAt); } },
        { t: '입금액', cls: 'num', f: function (u) {
            return u.cost.paid ? JL.won(u.cost.paid) : '<span class="of-pill warn">미입금</span>';
        } },
        { t: '차액', cls: 'num', f: function (u) {
            var d = u.cost.diff;
            if (!u.cost.paid || !d) return '';
            return '<b class="' + (d > 0 ? 'of-amt-back' : 'of-amt-more') + '">' + (d > 0 ? '+' : '−') + JL.won(Math.abs(d)) + '</b>';
        } },
        { t: '단계', f: function (u) { return esc(u.step); } },
        { t: '', f: function (u) {
            return (u.cost.items && u.cost.items.length)
              ? '<button type="button" class="btn sm" data-bill="' + JL.unitKey(u.dong, u.ho) + '">명세서</button>' : '';
        } }
      ], list);

    el.querySelectorAll('[data-bill]').forEach(function (b) {
      b.addEventListener('click', function () { showBill(cx, JL.db.complexes[cx].units[b.dataset.bill]); });
    });

    el.querySelectorAll('[data-cf]').forEach(function (b) {
      b.addEventListener('click', function () { costFilter = b.dataset.cf; ui.go('progress'); });
    });
    $('cCsv').addEventListener('click', function () {
      var rows = [['동', '호', '명의자', '등기비용', '입금일', '입금액', '차액', '단계']];
      list.forEach(function (u) {
        rows.push([u.dong, u.ho, (u.owners[0] || {}).name, u.cost.total, u.cost.paidAt, u.cost.paid, u.cost.diff, u.step]);
      });
      JL.csv(rows, cx + '_등기비용_' + JL.today() + '.csv');
    });
  }

  /** 등기비용 명세서 — 전화로 설명하는 대신 이 화면을 인쇄하거나 캡처해 보낸다. */
  JL.billHtml = function (cx, u) {
    var items = u.cost.items || [];
    var sum = items.reduce(function (a, it) { return a + (Number(it.v) || 0); }, 0);
    var who = u.owners.map(function (o) { return o.name; }).filter(Boolean).join(' · ');
    var d = u.cost.diff;
    return '<div class="of-bill">' +
      '<header><h4>등기비용 명세서</h4><p>' + esc(cx) + ' ' + esc(u.dong) + '동 ' + esc(u.ho) + '호' +
        (who ? ' · ' + esc(who) : '') + '</p></header>' +
      '<table><tbody>' +
      items.map(function (it) {
        return '<tr class="' + (it.v ? '' : 'zero') + '"><th>' + esc(it.k) + '</th><td>' + JL.won(it.v) + '원</td></tr>';
      }).join('') +
      '</tbody><tfoot>' +
        '<tr class="sum"><th>등기비용 합계</th><td>' + JL.won(u.cost.total || sum) + '원</td></tr>' +
        (u.cost.paid ? '<tr><th>입금하신 금액' + (u.cost.paidAt ? ' <small>' + esc(u.cost.paidAt) + '</small>' : '') + '</th><td>' + JL.won(u.cost.paid) + '원</td></tr>' : '') +
        (u.cost.paid && d ? '<tr class="' + (d > 0 ? 'back' : 'more') + '"><th>' + (d > 0 ? '돌려드릴 금액' : '더 내실 금액') + '</th><td>' + JL.won(Math.abs(d)) + '원</td></tr>' : '') +
      '</tfoot></table>' +
      (u.cost.total && sum && Math.abs(sum - u.cost.total) > 10
        ? '<p class="of-note warn">항목을 더한 값(' + JL.won(sum) + '원)이 엑셀 합계와 다릅니다. 엑셀을 확인하십시오.</p>' : '') +
      '</div>';
  };

  function showBill(cx, u) {
    ui.dialog({
      title: '',
      body: JL.billHtml(cx, u),
      ok: '인쇄',
      onOk: function () {
        var w = window.open('', '_blank', 'width=720,height=900');
        if (!w) { ui.toast('팝업이 막혀 있습니다. 브라우저에서 팝업을 허용해 주십시오.', 'err'); return false; }
        w.document.write('<!doctype html><meta charset="utf-8"><title>등기비용 명세서</title>' +
          '<link rel="stylesheet" href="../assets/css/office.css">' +
          '<body class="of-print">' + JL.billHtml(cx, u) + '<script>onload=function(){print()}<\/script>');
        w.document.close();
        return false;
      }
    });
  }

  /* ── 탭: 권리증 수령주소 ───────────────────── */
  function renderCert(el, cx, units) {
    var withAddr = units.filter(function (u) { return u.cert.addr; });
    var sent = withAddr.filter(function (u) { return u.cert.sentAt; }).length;
    var ready = units.filter(function (u) { return JL.STEPS.indexOf(u.step) >= JL.DONE_FROM && !u.cert.sentAt; });
    var list = units.filter(match);

    el.innerHTML =
      ui.stats([
        { k: '주소 받은 세대', v: withAddr.length.toLocaleString(), s: '전체 ' + units.length.toLocaleString() },
        { k: '발송 완료', v: sent.toLocaleString(), tone: 'ok' },
        { k: '등기 끝났는데 미발송', v: ready.length.toLocaleString(), tone: ready.length ? 'warn' : '' },
        { k: '주소 없음', v: (units.length - withAddr.length).toLocaleString(), tone: units.length - withAddr.length ? 'warn' : '' }
      ]) +
      '<div class="of-bar"><span class="of-sp"></span>' +
        (ready.length ? '<button type="button" class="btn btn--fill" id="certSend">등기 끝난 ' + ready.length + '세대 오늘 발송으로 기록</button>' : '') +
        '<button type="button" class="btn" id="certCsv">발송용 CSV</button></div>' +
      ui.table([
        { t: '동', k: 'dong', cls: 'num' },
        { t: '호', k: 'ho', cls: 'num' },
        { t: '명의자', f: function (u) { return esc((u.owners[0] || {}).name); } },
        { t: '권리증 받을 주소', f: function (u) {
            return u.cert.addr ? esc(u.cert.addr) : '<span class="of-pill warn">주소 없음</span>';
        } },
        { t: '발송일', cls: 'num', f: function (u) {
            return u.cert.sentAt ? esc(u.cert.sentAt) : (JL.STEPS.indexOf(u.step) >= JL.DONE_FROM ? '<span class="of-pill warn">발송 대기</span>' : '');
        } },
        { t: '단계', f: function (u) { return esc(u.step); } }
      ], list);

    if ($('certSend')) $('certSend').addEventListener('click', function () {
      var d = JL.today();
      ready.forEach(function (u) { u.cert.sentAt = d; });
      JL.touch(); ui.go('progress');
      ui.toast(ready.length + '세대를 ' + d + ' 발송으로 기록했습니다.', 'ok');
    });
    $('certCsv').addEventListener('click', function () {
      var rows = [['동', '호', '명의자', '받을 주소', '발송일']];
      list.forEach(function (u) { rows.push([u.dong, u.ho, (u.owners[0] || {}).name, u.cert.addr, u.cert.sentAt]); });
      JL.csv(rows, cx + '_권리증주소_' + JL.today() + '.csv');
      ui.toast('주소가 들어 있습니다. 발송이 끝나면 파일을 지우십시오.', 'ok');
    });
  }

  /* ── 탭: 채권환불 ──────────────────────────── */
  function renderRefund(el, cx, units) {
    var req = units.filter(function (u) { return u.refund.account; });
    var done = req.filter(function (u) { return u.refund.status === '완료'; }).length;
    var list = req.filter(match);

    el.innerHTML =
      ui.stats([
        { k: '환불 신청', v: req.length.toLocaleString() },
        { k: '처리 완료', v: done.toLocaleString(), tone: 'ok' },
        { k: '처리 대기', v: (req.length - done).toLocaleString(), tone: req.length - done ? 'warn' : '' }
      ]) +
      '<div class="of-callout warn"><b>계좌번호는 이 PC 밖으로 나가지 않습니다.</b> ' +
        '서버 업로드본에도 담지 않습니다. CSV 로 내려받았다면 이체가 끝난 뒤 바로 지우십시오.</div>' +
      '<div class="of-bar"><span class="of-sp"></span><button type="button" class="btn" id="rfCsv">이체용 CSV</button></div>' +
      ui.table([
        { t: '동', k: 'dong', cls: 'num' },
        { t: '호', k: 'ho', cls: 'num' },
        { t: '명의자', f: function (u) { return esc((u.owners[0] || {}).name); } },
        { t: '은행', f: function (u) { return esc(u.refund.bank); } },
        { t: '계좌', cls: 'num', f: function (u) { return esc(u.refund.account); } },
        { t: '돌려드릴 금액', cls: 'num', f: function (u) { return u.cost.diff > 0 ? JL.won(u.cost.diff) : ''; } },
        { t: '처리', f: function (u) {
            var k = JL.unitKey(u.dong, u.ho), ok = u.refund.status === '완료';
            return '<button type="button" class="btn sm' + (ok ? '' : ' btn--fill') + '" data-rf="' + k + '">' +
              (ok ? '완료 · 되돌리기' : '환불 완료로') + '</button>';
        } }
      ], list, { empty: '채권환불 신청 계좌가 들어온 세대가 없습니다.' });

    var c = JL.db.complexes[cx];
    el.querySelectorAll('[data-rf]').forEach(function (b) {
      b.addEventListener('click', function () {
        var u = c.units[b.dataset.rf];
        u.refund.status = u.refund.status === '완료' ? '' : '완료';
        JL.touch(); ui.go('progress');
      });
    });
    $('rfCsv').addEventListener('click', function () {
      var rows = [['동', '호', '명의자', '은행', '계좌', '돌려드릴금액', '처리']];
      list.forEach(function (u) {
        rows.push([u.dong, u.ho, (u.owners[0] || {}).name, u.refund.bank, u.refund.account,
          u.cost.diff > 0 ? u.cost.diff : '', u.refund.status]);
      });
      JL.csv(rows, cx + '_채권환불_' + JL.today() + '.csv');
      ui.toast('계좌번호가 들어 있습니다. 이체가 끝나면 파일을 지우십시오.', 'ok');
    });
  }

  function match(u) {
    if (!q) return true;
    return (u.dong + ' ' + u.ho + ' ' + u.owners.map(function (o) { return o.name; }).join(' ')).indexOf(q) >= 0;
  }

  /* ── 모듈 ──────────────────────────────────── */
  JL.mod('progress', {
    title: '등기진행',
    icon: '▤',
    lead: '진행 단계, 등기비용, 권리증 수령주소, 채권환불을 세대별로 관리합니다.',

    render: function (el, cx) {
      var TABS = [
        ['flow', '진행 현황'], ['cost', '등기비용'], ['cert', '권리증 수령주소'], ['refund', '채권환불']
      ];
      var head =
        '<div class="of-bar">' +
          '<button type="button" class="btn btn--fill" id="pgImport">등기 엑셀 불러오기</button>' +
          (cx ? '<input type="search" id="pgQ" class="of-search" placeholder="동·호·이름으로 찾기" value="' + esc(q) + '">' : '') +
          '<span class="of-sp"></span>' +
          (cx ? '<button type="button" class="btn" id="pgExport">서버 업로드본</button>' : '') +
        '</div>';

      if (!cx) {
        el.innerHTML = head +
          '<div class="of-blank"><h3>쓰시던 엑셀을 그대로 올리십시오</h3>' +
          '<p>제목 줄과 여덟 단계, 등기비용, 권리증 주소, 환불 계좌를 알아서 찾습니다.<br>' +
          '동·호·성명이 0 인 빈 줄은 건너뜁니다.</p></div>';
        $('pgImport').addEventListener('click', function () { importExcel(''); });
        return;
      }

      el.innerHTML = head +
        '<nav class="of-tabs">' + TABS.map(function (t) {
          return '<button type="button" data-tab="' + t[0] + '" class="' + (tab === t[0] ? 'on' : '') + '">' + t[1] + '</button>';
        }).join('') + '</nav>' +
        '<div id="pgBody"></div>';

      var units = JL.units(cx);
      var body = $('pgBody');
      ({ flow: renderFlow, cost: renderCost, cert: renderCert, refund: renderRefund }[tab])(body, cx, units);

      $('pgImport').addEventListener('click', function () { importExcel(cx); });
      $('pgExport').addEventListener('click', function () { exportServer(cx); });
      el.querySelectorAll('[data-tab]').forEach(function (b) {
        b.addEventListener('click', function () { tab = b.dataset.tab; sel = {}; ui.go('progress'); });
      });
      $('pgQ').addEventListener('input', function () {
        q = this.value.trim();
        var pos = this.selectionStart;
        ui.go('progress');
        var n = $('pgQ'); n.focus(); n.setSelectionRange(pos, pos);
      });
    }
  });

  // 시험용 — 파일 대화상자 없이 엑셀을 넣는다
  JL._importBuffer = function (name, ab) {
    var res = parseWorkbook(ab), c = JL.complex(name);
    res.units.forEach(function (u) { c.units[JL.unitKey(u.dong, u.ho)] = u; });
    JL.touch();
    return res;
  };

})(window);
