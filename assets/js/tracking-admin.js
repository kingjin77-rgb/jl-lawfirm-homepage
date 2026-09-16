/* 등기 진행 관리 — 직원용
 *
 * 설계 원칙 하나만 기억하면 된다.
 * 세대주 이름과 생년월일은 개인정보다. 홈페이지 저장소(GitHub)에 올리지 않는다.
 * 그래서 이 화면은 다른 관리자 페이지와 달리 GitHub API 를 쓰지 않는다.
 * 자료는 브라우저 안에만 있고, 저장은 암호화 파일 내려받기로 한다.
 *
 * 엑셀은 우리가 쓰는 고정 양식이다. 그래서 열을 물어보지 않고 그대로 읽는다.
 *   1~3행 : 병합된 큰 제목
 *   4행   : 실제 제목 줄
 *   5행~  : 세대
 * 진행 단계는 한 칸이 아니라 여덟 개 열에 "완료" 로 표시돼 있다.
 * 마지막으로 완료된 칸이 그 세대의 현재 단계다.
 *
 * 은행·계좌는 읽지 않는다. 채권 환불용 금융정보이고 조회에 쓸 일이 없다.
 */
(function () {
  'use strict';

  // 실제 업무에서 쓰는 여덟 단계. 엑셀 제목 줄과 같은 순서다.
  var STEPS = [
    '서류수령', '취득세신고', '등기비용통보', '등기비입금확인',
    '건설사 등기서류수령', '등기소서류접수', '등기완료', '권리증교부'
  ];
  var NOT_YET = '접수 전';                 // 완료된 칸이 하나도 없을 때
  var DONE_FROM = 6;                       // 등기완료부터 완료로 본다

  var $ = function (id) { return document.getElementById(id); };
  var data = {};        // { 단지명: [세대, ...] }
  var sel = {};
  var filterStep = null;
  var undo = null;      // 직전 상태 한 벌. 일괄 변경 되돌리기용

  /* ── 거들기 ───────────────────────────────── */

  function msg(text, kind) {
    var n = $('msg');
    n.textContent = text || '';
    n.className = 'tk-msg' + (kind ? ' ' + kind : '');
  }

  function today() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') +
      '-' + String(d.getDate()).padStart(2, '0');
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function digits(v) { return String(v == null ? '' : v).replace(/[^0-9]/g, ''); }

  function won(v) {
    var n = Number(v);
    return isFinite(n) && n !== 0 ? n.toLocaleString('ko-KR') : (n === 0 ? '0' : '');
  }

  /** 엑셀은 날짜를 Date·문자·숫자로 제각각 준다. 생년월일 6자리로 맞춘다. */
  function birth6(v) {
    if (v instanceof Date) {
      return String(v.getFullYear()).slice(2) +
        String(v.getMonth() + 1).padStart(2, '0') +
        String(v.getDate()).padStart(2, '0');
    }
    var s = digits(v);
    if (s.length >= 13) return s.slice(0, 6);   // 주민번호 전체가 들어온 경우
    if (s.length === 8) return s.slice(2);      // 19900101
    return s.slice(0, 6);
  }

  function dateOnly(v) {
    if (v instanceof Date) {
      return v.getFullYear() + '-' + String(v.getMonth() + 1).padStart(2, '0') +
        '-' + String(v.getDate()).padStart(2, '0');
    }
    var s = String(v == null ? '' : v).trim();
    var m = s.match(/(\d{2,4})\D+(\d{1,2})\D+(\d{1,2})/);
    if (!m) return '';
    var y = m[1].length === 2 ? '20' + m[1] : m[1];
    return y + '-' + m[2].padStart(2, '0') + '-' + m[3].padStart(2, '0');
  }

  function complexes() {
    return Object.keys(data).sort(function (a, b) { return a.localeCompare(b, 'ko'); });
  }
  function cur() { return $('complex').value; }
  function rowsOf(n) { return data[n] || []; }

  /* ── 그리기 ───────────────────────────────── */

  function drawComplexes(keep) {
    var s = $('complex'), list = complexes();
    s.innerHTML = list.length
      ? list.map(function (n) {
          return '<option value="' + esc(n) + '">' + esc(n) + ' (' + rowsOf(n).length + '세대)</option>';
        }).join('')
      : '<option value="">— 단지 없음 —</option>';
    if (keep && list.indexOf(keep) >= 0) s.value = keep;
  }

  function drawStats() {
    var rows = rowsOf(cur()), box = $('stat');
    if (!rows.length) { box.innerHTML = ''; return; }
    var count = {};
    rows.forEach(function (r) { count[r.step] = (count[r.step] || 0) + 1; });
    var html = '<button type="button" data-step="" class="' + (filterStep === null ? 'on' : '') +
      '">전체<b>' + rows.length + '</b></button>';
    [NOT_YET].concat(STEPS).forEach(function (st) {
      if (!count[st]) return;
      html += '<button type="button" data-step="' + esc(st) + '" class="' +
        (filterStep === st ? 'on' : '') + '">' + esc(st) + '<b>' + count[st] + '</b></button>';
    });
    box.innerHTML = html;
  }

  function visibleRows() {
    var q = $('q').value.trim();
    return rowsOf(cur()).map(function (r, i) { return { r: r, i: i }; })
      .filter(function (o) {
        if (filterStep !== null && o.r.step !== filterStep) return false;
        if (!q) return true;
        return (o.r.dong + ' ' + o.r.ho + ' ' + o.r.name + ' ' + (o.r.name2 || '')).indexOf(q) >= 0;
      });
  }

  function stepSelect(value, idx) {
    return '<select data-i="' + idx + '" class="jsStep">' +
      [NOT_YET].concat(STEPS).map(function (s) {
        return '<option' + (s === value ? ' selected' : '') + '>' + esc(s) + '</option>';
      }).join('') + '</select>';
  }

  function draw() {
    var list = visibleRows(), tb = $('rows');
    $('empty').hidden = list.length > 0;
    tb.innerHTML = list.map(function (o) {
      var r = o.r;
      var done = STEPS.indexOf(r.step) >= DONE_FROM;
      var who = esc(r.name) + (r.name2 ? '<span class="tk-co">' + esc(r.name2) + '</span>' : '');
      var diff = Number(r.diff);
      var diffCell = isFinite(diff) && diff !== 0
        ? '<span class="' + (diff < 0 ? 'tk-minus' : '') + '">' + won(diff) + '</span>' : '';
      return '<tr>' +
        '<td><input type="checkbox" class="jsSel" data-i="' + o.i + '"' +
          (sel[o.i] ? ' checked' : '') + '></td>' +
        '<td class="num">' + esc(r.dong) + '</td>' +
        '<td class="num">' + esc(r.ho) + '</td>' +
        '<td>' + who + '</td>' +
        '<td class="num">' + esc(r.birth) + '</td>' +
        '<td>' + stepSelect(r.step, o.i) +
          ' <span class="tk-step' + (done ? ' done' : '') + '">' + (done ? '완료' : '진행') + '</span></td>' +
        '<td class="num">' + won(r.total) + '</td>' +
        '<td class="num">' + won(r.paid) + '</td>' +
        '<td class="num">' + diffCell + '</td>' +
        '<td><input type="text" class="jsMemo" data-i="' + o.i + '" value="' + esc(r.memo || '') +
          '" placeholder="고객에게 보이는 안내"></td>' +
        '</tr>';
    }).join('');
    drawStats();
    drawBulk();
  }

  function drawBulk() {
    var n = Object.keys(sel).filter(function (k) { return sel[k]; }).length;
    $('bulkBar').hidden = n === 0 && !undo;
    $('selCount').textContent = n ? n + '개 세대 선택됨' : '';
    $('btnBulk').hidden = n === 0;
    $('bulkStep').hidden = n === 0;
    $('btnClearSel').hidden = n === 0;
    $('btnUndo').hidden = !undo;
    if (undo) $('btnUndo').textContent = '되돌리기 (' + undo.label + ')';
  }

  /* ── 엑셀 읽기 ─────────────────────────────── */

  /** 제목 줄을 찾는다. 양식이 조금 밀려도 견디게 한다. */
  function findHeader(aoa) {
    for (var i = 0; i < Math.min(aoa.length, 12); i++) {
      var row = (aoa[i] || []).map(function (c) { return String(c == null ? '' : c).trim(); });
      var hasDong = row.some(function (c) { return c === '동'; });
      var hasHo = row.some(function (c) { return c === '호' || c === '호수'; });
      var hasName = row.some(function (c) { return c.indexOf('성명') >= 0 || c === '이름'; });
      if (hasDong && hasHo && hasName) return i;
    }
    return -1;
  }

  function colOf(head, test) {
    for (var i = 0; i < head.length; i++) { if (test(head[i], i)) return i; }
    return -1;
  }

  function readXlsx(file) {
    var fr = new FileReader();
    fr.onload = function (e) {
      var wb;
      try {
        wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array', cellDates: true });
      } catch (err) {
        msg('엑셀을 읽지 못했습니다. 파일이 손상되었거나 지원하지 않는 형식입니다.', 'err');
        return;
      }
      var ws = wb.Sheets[wb.SheetNames[0]];
      var aoa = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false, defval: '' });
      var hi = findHeader(aoa);
      if (hi < 0) {
        msg('제목 줄을 찾지 못했습니다. 동·호·성명이 있는 줄이 필요합니다.', 'err');
        return;
      }
      var head = (aoa[hi] || []).map(function (c) { return String(c == null ? '' : c).trim(); });

      var cDong  = colOf(head, function (h) { return h === '동'; });
      var cHo    = colOf(head, function (h) { return h === '호' || h === '호수'; });
      var cName  = colOf(head, function (h) { return h.indexOf('성명') >= 0 || h === '이름'; });
      var cBirth = colOf(head, function (h) { return h.indexOf('주민') >= 0 || h.indexOf('생년') >= 0; });

      // 여덟 단계 열 — "1.서류수령" 처럼 번호가 붙어 있다
      var stepCols = STEPS.map(function (s, k) {
        var key = s.replace(/\s+/g, '');
        return colOf(head, function (h) {
          var t = h.replace(/\s+/g, '');
          return t === (k + 1) + '.' + key || t === key || t.indexOf((k + 1) + '.' + key) === 0;
        });
      });
      if (stepCols.filter(function (c) { return c >= 0; }).length < 4) {
        msg('진행 단계 열을 찾지 못했습니다. 제목 줄에 "1.서류수령" 같은 칸이 있어야 합니다.', 'err');
        return;
      }

      var cTotal = colOf(head, function (h) { return h.indexOf('등기비용합계') >= 0; });
      var cPaid  = colOf(head, function (h) { return h === '입금액'; });
      var cDiff  = colOf(head, function (h) { return h === '차액'; });
      var cLack  = colOf(head, function (h) { return h.indexOf('미비서류') >= 0; });
      var cSend  = colOf(head, function (h) { return h === '발송일'; });

      // 공동명의 — 성명 바로 오른쪽 칸에 두 번째 이름이 들어온다
      var cName2 = (cName >= 0 && cName + 1 !== cBirth) ? cName + 1 : -1;
      var cBirth2 = (cBirth >= 0) ? cBirth + 1 : -1;

      var out = [], skipped = 0, broken = 0;
      for (var i = hi + 1; i < aoa.length; i++) {
        var row = aoa[i] || [];
        var dong = digits(row[cDong]), ho = digits(row[cHo]);
        var name = String(row[cName] == null ? '' : row[cName]).trim();
        // 엑셀에는 나중에 채우려고 만들어 둔 빈 줄이 깔려 있다.
        // 그 줄은 동·호·성명이 모두 숫자 0 이다. 세대가 아니므로 건너뛴다.
        if (!dong || !ho || !name || Number(dong) === 0 || Number(ho) === 0 || name === '0') {
          skipped++; continue;
        }
        if (/^#(REF|VALUE|N\/A|DIV)/i.test(name)) { broken++; continue; }

        // 마지막으로 "완료" 인 칸이 현재 단계다
        var last = -1;
        for (var k = 0; k < stepCols.length; k++) {
          if (stepCols[k] < 0) continue;
          if (String(row[stepCols[k]] || '').trim() === '완료') last = k;
        }

        var n2 = cName2 >= 0 ? String(row[cName2] == null ? '' : row[cName2]).trim() : '';
        if (/^#(REF|VALUE|N\/A|DIV)/i.test(n2)) n2 = '';

        out.push({
          dong: dong, ho: ho,
          name: name, birth: cBirth >= 0 ? birth6(row[cBirth]) : '',
          name2: n2, birth2: n2 && cBirth2 >= 0 ? birth6(row[cBirth2]) : '',
          step: last < 0 ? NOT_YET : STEPS[last],
          total: cTotal >= 0 ? Number(digits(row[cTotal])) || 0 : 0,
          paid:  cPaid  >= 0 ? Number(digits(row[cPaid]))  || 0 : 0,
          diff:  cDiff  >= 0 ? (String(row[cDiff]).indexOf('-') === 0 ? -1 : 1) *
                               (Number(digits(row[cDiff])) || 0) : 0,
          lack:  cLack  >= 0 ? String(row[cLack] || '').trim() : '',
          sentAt: cSend >= 0 ? dateOnly(row[cSend]) : '',
          memo: '',
          at: today()
        });
      }

      if (!out.length) {
        msg('읽어들인 세대가 없습니다. 파일을 확인해 주십시오.', 'err');
        return;
      }

      var key = cur() || file.name.replace(/\.(xlsx|xls|csv)$/i, '');
      merge(key, out, skipped, broken);
    };
    fr.readAsArrayBuffer(file);
  }

  /** 같은 단지를 다시 올리면 동·호로 맞춰 갱신한다. 직원이 적은 메모는 지키고 덮지 않는다. */
  function merge(key, out, skipped, broken) {
    var merged = rowsOf(key).slice(), idx = {};
    merged.forEach(function (r, i) { idx[r.dong + '-' + r.ho] = i; });

    var added = 0, updated = 0, moved = 0;
    out.forEach(function (r) {
      var k = r.dong + '-' + r.ho, t = merged[idx[k]];
      if (t) {
        if (t.step !== r.step) moved++;
        r.memo = t.memo || '';              // 손으로 적은 안내는 지킨다
        merged[idx[k]] = r;
        updated++;
      } else {
        merged.push(r); idx[k] = merged.length - 1; added++;
      }
    });
    merged.sort(function (a, b) {
      return (Number(a.dong) - Number(b.dong)) || (Number(a.ho) - Number(b.ho));
    });
    data[key] = merged;

    sel = {}; filterStep = null; undo = null;
    drawComplexes(key);
    $('complex').value = key;
    draw();
    msg('읽어들였습니다. 새 세대 ' + added + '개, 갱신 ' + updated + '개' +
      (moved ? ' (단계가 바뀐 세대 ' + moved + '개)' : '') +
      (skipped ? ' · 건너뜀 ' + skipped + '줄' : '') +
      (broken ? ' · 수식 오류 ' + broken + '줄' : ''), 'ok');
  }

  /* ── 암호화 저장 ───────────────────────────── */

  var enc = new TextEncoder(), dec = new TextDecoder();

  function deriveKey(pw, salt) {
    return crypto.subtle.importKey('raw', enc.encode(pw), 'PBKDF2', false, ['deriveKey'])
      .then(function (base) {
        return crypto.subtle.deriveKey(
          { name: 'PBKDF2', salt: salt, iterations: 240000, hash: 'SHA-256' },
          base, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
      });
  }

  function download(blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  function saveEncrypted() {
    if (!complexes().length) { msg('저장할 자료가 없습니다.', 'err'); return; }
    var pw = prompt('저장할 파일의 비밀번호를 정하십시오.\n이 비밀번호가 없으면 파일을 다시 열 수 없습니다.');
    if (!pw) return;
    if (pw.length < 8) { msg('비밀번호는 8자 이상으로 정해 주십시오.', 'err'); return; }
    if (prompt('확인을 위해 한 번 더 입력하십시오.') !== pw) {
      msg('두 번 입력한 비밀번호가 다릅니다.', 'err'); return;
    }
    var salt = crypto.getRandomValues(new Uint8Array(16));
    var iv = crypto.getRandomValues(new Uint8Array(12));
    deriveKey(pw, salt).then(function (key) {
      return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key,
        enc.encode(JSON.stringify({ v: 2, savedAt: today(), data: data })));
    }).then(function (buf) {
      var body = new Uint8Array(buf), out = new Uint8Array(28 + body.length);
      out.set(salt, 0); out.set(iv, 16); out.set(body, 28);
      download(new Blob([out], { type: 'application/octet-stream' }), '등기진행_' + today() + '.jlreg');
      msg('암호화 저장본을 내려받았습니다. 파일과 비밀번호는 따로 보관하십시오.', 'ok');
    }).catch(function () {
      msg('암호화에 실패했습니다. 브라우저를 최신판으로 올린 뒤 다시 시도해 주십시오.', 'err');
    });
  }

  function openEncrypted(file) {
    var pw = prompt('저장할 때 정한 비밀번호를 입력하십시오.');
    if (!pw) return;
    var fr = new FileReader();
    fr.onload = function (e) {
      var raw = new Uint8Array(e.target.result);
      if (raw.length < 29) { msg('저장본 형식이 아닙니다.', 'err'); return; }
      deriveKey(pw, raw.slice(0, 16)).then(function (key) {
        return crypto.subtle.decrypt({ name: 'AES-GCM', iv: raw.slice(16, 28) }, key, raw.slice(28));
      }).then(function (buf) {
        var obj = JSON.parse(dec.decode(buf));
        data = obj.data || {};
        sel = {}; filterStep = null; undo = null;
        drawComplexes(); draw();
        msg((obj.savedAt || '') + ' 저장본을 열었습니다. 단지 ' + complexes().length + '개.', 'ok');
      }).catch(function () {
        msg('열지 못했습니다. 비밀번호가 다르거나 파일이 손상되었습니다.', 'err');
      });
    };
    fr.readAsArrayBuffer(file);
  }

  /* ── 서버 업로드본 ─────────────────────────── */

  function sha256hex(text) {
    return crypto.subtle.digest('SHA-256', enc.encode(text)).then(function (buf) {
      return Array.prototype.map.call(new Uint8Array(buf), function (b) {
        return b.toString(16).padStart(2, '0');
      }).join('');
    });
  }

  function exportForServer() {
    var name = cur(), rows = rowsOf(name);
    if (!rows.length) { msg('내보낼 세대가 없습니다.', 'err'); return; }
    var salt = prompt(
      '서버와 맞출 확인용 문구를 입력하십시오.\n' +
      '서버 설정(config.php)의 verify_salt 와 똑같아야 조회가 됩니다.');
    if (!salt) return;

    // 공동명의는 두 사람 모두로 조회되게 해시를 둘 만든다.
    Promise.all(rows.map(function (r) {
      var jobs = [sha256hex(salt + '|' + r.name + '|' + r.birth)];
      if (r.name2 && r.birth2) jobs.push(sha256hex(salt + '|' + r.name2 + '|' + r.birth2));
      return Promise.all(jobs).then(function (hs) {
        return {
          dong: r.dong, ho: r.ho, vhash: hs,
          step: r.step, at: r.at || today(), memo: r.memo || '',
          total: r.total || 0, paid: r.paid || 0, diff: r.diff || 0,
          lack: r.lack || '', sentAt: r.sentAt || ''
        };
      });
    })).then(function (list) {
      download(new Blob([JSON.stringify({
        complex: name, steps: STEPS, exportedAt: today(), households: list
      }, null, 1)], { type: 'application/json' }),
        'upload_' + name.replace(/\s+/g, '_') + '_' + today() + '.json');
      msg('서버 업로드본을 내려받았습니다. 이름·생년월일은 해시로만 들어 있고, 은행·계좌는 담지 않습니다.', 'ok');
    });
  }

  /* ── 이어 붙이기 ───────────────────────────── */

  function snapshot(label) {
    undo = { label: label, rows: JSON.parse(JSON.stringify(rowsOf(cur()))), key: cur() };
  }

  function init() {
    $('bulkStep').innerHTML = [NOT_YET].concat(STEPS)
      .map(function (s) { return '<option>' + esc(s) + '</option>'; }).join('');

    $('btnImport').addEventListener('click', function () {
      if (!complexes().length && !cur()) {
        var n = prompt('단지 이름을 입력하십시오.');
        if (!n) return;
        data[n] = []; drawComplexes(n); $('complex').value = n;
      }
      $('fileXlsx').value = ''; $('fileXlsx').click();
    });
    $('fileXlsx').addEventListener('change', function () {
      if (this.files[0]) readXlsx(this.files[0]);
    });
    $('btnOpen').addEventListener('click', function () { $('fileJson').value = ''; $('fileJson').click(); });
    $('fileJson').addEventListener('change', function () {
      if (this.files[0]) openEncrypted(this.files[0]);
    });
    $('btnSave').addEventListener('click', saveEncrypted);
    $('btnExport').addEventListener('click', exportForServer);

    $('complex').addEventListener('change', function () { sel = {}; filterStep = null; undo = null; draw(); });
    $('q').addEventListener('input', draw);

    $('stat').addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      filterStep = b.dataset.step || null;
      draw();
    });

    $('rows').addEventListener('change', function (e) {
      var t = e.target, i = parseInt(t.dataset.i, 10);
      if (t.classList.contains('jsStep')) {
        snapshot('단계 변경 1건');
        var r = rowsOf(cur())[i];
        r.step = t.value; r.at = today();
        draw();
      } else if (t.classList.contains('jsSel')) {
        sel[i] = t.checked; drawBulk();
      }
    });
    $('rows').addEventListener('input', function (e) {
      if (!e.target.classList.contains('jsMemo')) return;
      rowsOf(cur())[parseInt(e.target.dataset.i, 10)].memo = e.target.value;
    });

    $('chkAll').addEventListener('change', function () {
      var on = this.checked;
      visibleRows().forEach(function (o) { sel[o.i] = on; });
      draw();
    });
    $('btnClearSel').addEventListener('click', function () { sel = {}; draw(); });

    $('btnBulk').addEventListener('click', function () {
      var step = $('bulkStep').value, rows = rowsOf(cur()), n = 0;
      snapshot('일괄 변경');
      Object.keys(sel).forEach(function (k) {
        if (!sel[k]) return;
        rows[k].step = step; rows[k].at = today(); n++;
      });
      undo.label = n + '건 → ' + step;
      sel = {}; draw();
      msg(n + '개 세대를 "' + step + '" 단계로 바꿨습니다. 잘못 눌렀으면 되돌리기를 누르십시오.', 'ok');
    });

    $('btnUndo').addEventListener('click', function () {
      if (!undo) return;
      data[undo.key] = undo.rows;
      var label = undo.label;
      undo = null; sel = {};
      drawComplexes(undo && undo.key); draw();
      msg('되돌렸습니다 (' + label + ').', 'ok');
    });

    window.addEventListener('beforeunload', function (e) {
      if (!complexes().length) return;
      e.preventDefault(); e.returnValue = '';
    });

    drawComplexes();
    draw();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }

  // 화면 확인용 — 콘솔에서 표본을 넣어 볼 때 쓴다.
  window.__jltrackSeed = function (name, list) {
    data[name] = list; drawComplexes(name); $('complex').value = name; draw();
  };
})();
