/* 등기업무 시스템 — 화면 뼈대
 *
 * 왼쪽 메뉴, 위쪽 단지 선택과 저장 단추, 가운데 본문, 알림 한 줄.
 * 모듈은 본문 영역만 그린다. 단지가 바뀌면 지금 메뉴를 다시 그린다.
 */
(function (W) {
  'use strict';
  var JL = W.JL, $ = JL.$, esc = JL.esc;

  var ui = JL.ui = {};
  var current = null;

  ui.complex = function () { return $('ofComplex').value; };

  /* ── 알림 ──────────────────────────────────── */
  var toastTimer = null;
  ui.toast = function (text, kind) {
    var n = $('ofToast');
    n.textContent = text;
    n.className = 'of-toast on' + (kind ? ' ' + kind : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { n.className = 'of-toast'; }, kind === 'err' ? 6000 : 3500);
  };

  /* ── 대화상자 ──────────────────────────────── */
  ui.dialog = function (opt) {
    var box = $('ofDialog');
    $('ofDialogTitle').textContent = opt.title || '';
    $('ofDialogBody').innerHTML = opt.body || '';
    $('ofDialogErr').textContent = '';
    var ok = $('ofDialogOk');
    ok.textContent = opt.ok || '확인';
    ok.onclick = function () {
      var r = opt.onOk ? opt.onOk(box) : true;
      if (r === false) return;
      if (r && typeof r === 'string') { $('ofDialogErr').textContent = r; return; }
      ui.closeDialog();
    };
    box.classList.add('on');
    var first = box.querySelector('input,select,textarea');
    if (first) setTimeout(function () { first.focus(); }, 50);
  };
  ui.closeDialog = function () { $('ofDialog').classList.remove('on'); };

  /** 비밀번호를 가리는 입력칸으로 받는다. prompt 는 글자가 그대로 보인다. */
  ui.askPassword = function (title, confirmTwice, cb) {
    ui.dialog({
      title: title,
      body:
        '<label class="of-fld"><span>비밀번호</span><input type="password" id="pw1" autocomplete="new-password"></label>' +
        (confirmTwice
          ? '<label class="of-fld"><span>한 번 더</span><input type="password" id="pw2" autocomplete="new-password"></label>' +
            '<p class="of-note">이 비밀번호를 잊으면 파일을 다시 열 수 없습니다. 8자 이상으로 정하십시오.</p>'
          : ''),
      ok: confirmTwice ? '암호화해 저장' : '열기',
      onOk: function () {
        var a = $('pw1').value;
        if (confirmTwice) {
          if (a.length < 8) return '8자 이상으로 정해 주십시오.';
          if (a !== $('pw2').value) return '두 번 입력한 비밀번호가 다릅니다.';
        } else if (!a) {
          return '비밀번호를 입력해 주십시오.';
        }
        cb(a);
        return true;
      }
    });
  };

  /* ── 단지 선택 ─────────────────────────────── */
  ui.drawComplexes = function (keep) {
    var s = $('ofComplex'), list = JL.complexNames();
    var was = keep || s.value;
    s.innerHTML = list.length
      ? list.map(function (n) {
          var c = JL.db.complexes[n];
          return '<option value="' + esc(n) + '">' + esc(n) + ' · ' +
            Object.keys(c.units).length.toLocaleString() + '세대' + (c.open ? '' : ' (조회 닫힘)') + '</option>';
        }).join('')
      : '<option value="">단지를 먼저 추가하십시오</option>';
    if (was && list.indexOf(was) >= 0) s.value = was;
  };

  /* ── 메뉴 ──────────────────────────────────── */
  ui.drawMenu = function () {
    $('ofMenu').innerHTML = JL.order.map(function (id) {
      var m = JL.mods[id];
      return '<button type="button" data-mod="' + id + '" class="' + (id === current ? 'on' : '') + '">' +
        '<span class="ic" aria-hidden="true">' + m.icon + '</span>' +
        '<span class="t">' + esc(m.title) + '</span>' +
        (m.badge ? '<span class="bd" data-badge="' + id + '"></span>' : '') +
        '</button>';
    }).join('');
    ui.drawBadges();
  };

  ui.drawBadges = function () {
    JL.order.forEach(function (id) {
      var m = JL.mods[id], el = document.querySelector('[data-badge="' + id + '"]');
      if (!el || !m.badge) return;
      var n = m.badge();
      el.textContent = n ? n.toLocaleString() : '';
      el.hidden = !n;
    });
  };

  ui.go = function (id) {
    if (!JL.mods[id]) id = JL.order[0];
    // 같은 메뉴를 다시 그릴 때는 보던 자리를 지킨다.
    // 수백 세대 표를 내려가다 단계를 바꾸면 맨 위로 튀어 버리던 것을 막는다.
    var keepY = id === current ? window.pageYOffset : 0;
    current = id;
    try { history.replaceState(null, '', '#' + id); } catch (e) {}
    ui.drawMenu();
    var m = JL.mods[id];
    $('ofTitle').textContent = m.title;
    $('ofLead').textContent = m.lead || '';
    var body = $('ofBody');
    body.innerHTML = '';
    m.render(body, ui.complex());
    window.scrollTo(0, keepY);
  };

  ui.refresh = function () {
    ui.drawComplexes();
    if (current) ui.go(current);
  };

  ui.saveState = function () {
    var n = $('ofSaveState');
    if (!n) return;
    n.textContent = JL.dirty ? '저장하지 않은 변경이 있습니다' : (JL.db.savedAt ? JL.db.savedAt + ' 저장본' : '');
    n.className = 'of-state' + (JL.dirty ? ' warn' : '');
    ui.drawBadges();
  };

  /* ── 공용 조각 ─────────────────────────────── */

  /** 표. cols: [{t, k|f, cls}] */
  ui.table = function (cols, rows, opt) {
    opt = opt || {};
    if (!rows.length) {
      return '<div class="of-empty">' + esc(opt.empty || '표시할 자료가 없습니다.') + '</div>';
    }
    var max = opt.max || 500;
    var shown = rows.slice(0, max);
    return '<div class="of-tw"><table class="of-t"><thead><tr>' +
      cols.map(function (c) { return '<th class="' + (c.cls || '') + '">' + esc(c.t) + '</th>'; }).join('') +
      '</tr></thead><tbody>' +
      shown.map(function (r, i) {
        return '<tr' + (opt.rowAttr ? ' ' + opt.rowAttr(r, i) : '') + '>' +
          cols.map(function (c) {
            var v = c.f ? c.f(r, i) : esc(r[c.k]);
            return '<td class="' + (c.cls || '') + '">' + (v == null ? '' : v) + '</td>';
          }).join('') + '</tr>';
      }).join('') +
      '</tbody></table></div>' +
      (rows.length > max
        ? '<p class="of-more">' + rows.length.toLocaleString() + '건 중 ' + max + '건만 보입니다. 검색으로 좁히거나 CSV 로 내려받으십시오.</p>'
        : '');
  };

  /** 숫자 카드 줄 */
  ui.stats = function (items) {
    return '<div class="of-stats">' + items.map(function (it) {
      return '<div class="of-stat' + (it.tone ? ' ' + it.tone : '') + '">' +
        '<span class="k">' + esc(it.k) + '</span>' +
        '<b class="v' + (String(it.v).length > 10 ? ' long' : '') + '">' + esc(it.v) + '</b>' +
        (it.s ? '<span class="s">' + esc(it.s) + '</span>' : '') +
        '</div>';
    }).join('') + '</div>';
  };

  ui.needComplex = function (el, cx) {
    if (cx) return false;
    el.innerHTML =
      '<div class="of-blank"><h3>단지가 없습니다</h3>' +
      '<p>기본 정보에서 단지를 추가하거나, 등기진행에서 엑셀을 불러오면 단지가 만들어집니다.</p>' +
      '<button type="button" class="btn btn--fill" data-go="settings">기본 정보로 가기</button></div>';
    return true;
  };

  /* ── 시작 ──────────────────────────────────── */
  ui.start = function () {
    $('ofMenu').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-mod]');
      if (b) ui.go(b.dataset.mod);
    });
    document.addEventListener('click', function (e) {
      var g = e.target.closest('[data-go]');
      if (g) { e.preventDefault(); ui.go(g.dataset.go); }
    });
    $('ofComplex').addEventListener('change', function () { if (current) ui.go(current); });

    $('ofSave').addEventListener('click', function () {
      // 접수·설정·문자 기록만 있어도 저장할 자료다
      var db = JL.db;
      var any = JL.complexNames().length || db.surveys.length || db.intake.length ||
        db.sms.log.length || db.sms.templates.length;
      if (!JL.dirty && !any) {
        return ui.toast('저장할 자료가 없습니다.', 'err');
      }
      ui.askPassword('암호화해 저장', true, function (pw) {
        JL.save(pw).then(function () {
          ui.saveState();
          ui.toast('저장본을 내려받았습니다. 파일과 비밀번호는 따로 보관하십시오.', 'ok');
        }).catch(function () {
          ui.toast('암호화에 실패했습니다. 브라우저를 최신판으로 올려 주십시오.', 'err');
        });
      });
    });

    $('ofOpen').addEventListener('click', function () {
      JL.pickFile('.jlreg,.json', function (file) {
        ui.askPassword('저장본 열기', false, function (pw) {
          JL.open(file, pw).then(function (db) {
            ui.drawComplexes();
            ui.saveState();
            ui.go(current || JL.order[0]);
            ui.toast((db.savedAt || '') + ' 저장본을 열었습니다. 단지 ' + JL.complexNames().length + '개.', 'ok');
          }).catch(function () {
            ui.toast('열지 못했습니다. 비밀번호가 다르거나 파일이 손상되었습니다.', 'err');
          });
        });
      });
    });

    $('ofDialogCancel').addEventListener('click', ui.closeDialog);
    $('ofDialog').addEventListener('keydown', function (e) {
      if (e.key === 'Escape') ui.closeDialog();
      if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); $('ofDialogOk').click(); }
    });

    window.addEventListener('beforeunload', function (e) {
      if (!JL.dirty) return;
      e.preventDefault(); e.returnValue = '';
    });

    ui.drawComplexes();
    ui.saveState();
    var start = (location.hash || '').slice(1);
    ui.go(JL.mods[start] ? start : JL.order[0]);
  };

})(window);
