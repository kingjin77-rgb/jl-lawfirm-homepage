/* 등기 진행 관리 — 직원용

   설계 원칙 하나만 기억하면 된다.
   세대주 이름과 생년월일은 개인정보다. 홈페이지 저장소(GitHub)에 올리지 않는다.
   그래서 이 화면은 다른 관리자 페이지와 달리 GitHub API 를 쓰지 않는다.
   자료는 브라우저 안에만 있고, 저장은 암호화 파일 내려받기로 한다.

   서버가 생기면 "서버 업로드본 내보내기"로 만든 파일을 올린다.
   그때 조회 화면이 그 서버를 보게 되고, 이 화면은 그대로 쓰면 된다.
*/
(function () {
  'use strict';

  var STEPS = [
    '접수', '서류 검토', '보완', '취득세 신고',
    '취득세 납부', '등기 접수', '등기 완료', '권리증 교부'
  ];
  var DONE_FROM = 6;                       // 여기서부터 완료로 본다(등기 완료)
  var MAPKEY = 'jltrack.colmap';           // 단지별 열 맞춤 기억
  var FIELDS = [
    { k: 'dong',  t: '동',       need: true },
    { k: 'ho',    t: '호',       need: true },
    { k: 'name',  t: '이름',     need: true },
    { k: 'birth', t: '생년월일', need: false },
    { k: 'step',  t: '진행 단계', need: false },
    { k: 'memo',  t: '메모',     need: false }
  ];

  var $ = function (id) { return document.getElementById(id); };
  var data = {};            // { 단지명: [ {dong,ho,name,birth,step,memo,at}, ... ] }
  var sel = {};             // 선택된 행 인덱스
  var filterStep = null;
  var pending = null;       // 엑셀 읽는 중 임시 보관

  /* ── 거들기 ───────────────────────────────── */

  function msg(el, text, kind) {
    var n = $(el);
    n.textContent = text || '';
    n.className = 'tk-msg' + (kind ? ' ' + kind : '');
  }

  function today() {
    var d = new Date();
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  function digits(v) {
    return String(v == null ? '' : v).replace(/[^0-9]/g, '');
  }

  /** 생년월일은 엑셀에서 날짜·문자·숫자로 제각각 들어온다. 6자리로 맞춘다. */
  function birth6(v) {
    if (v instanceof Date) {
      return String(v.getFullYear()).slice(2) +
        String(v.getMonth() + 1).padStart(2, '0') +
        String(v.getDate()).padStart(2, '0');
    }
    var s = digits(v);
    if (s.length === 8) return s.slice(2);          // 19900101 → 900101
    if (s.length === 6) return s;
    return s.slice(0, 6);
  }

  function complexes() {
    return Object.keys(data).sort(function (a, b) { return a.localeCompare(b, 'ko'); });
  }

  function cur() { return $('complex').value; }

  function rowsOf(name) { return data[name] || []; }

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

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function drawStats() {
    var rows = rowsOf(cur()), box = $('stat');
    if (!rows.length) { box.innerHTML = ''; return; }
    var count = {};
    rows.forEach(function (r) { count[r.step] = (count[r.step] || 0) + 1; });
    var html = '<button type="button" data-step="" class="' + (filterStep === null ? 'on' : '') +
      '">전체<b>' + rows.length + '</b></button>';
    STEPS.forEach(function (st) {
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
        return (o.r.dong + ' ' + o.r.ho + ' ' + o.r.name).indexOf(q) >= 0;
      });
  }

  function stepSelect(value, idx) {
    return '<select data-i="' + idx + '" class="jsStep">' + STEPS.map(function (s) {
      return '<option' + (s === value ? ' selected' : '') + '>' + esc(s) + '</option>';
    }).join('') + '</select>';
  }

  function draw() {
    var list = visibleRows(), tb = $('rows');
    $('empty').hidden = list.length > 0;
    tb.innerHTML = list.map(function (o) {
      var r = o.r;
      var done = STEPS.indexOf(r.step) >= DONE_FROM;
      return '<tr>' +
        '<td><input type="checkbox" class="jsSel" data-i="' + o.i + '"' +
          (sel[o.i] ? ' checked' : '') + '></td>' +
        '<td class="num">' + esc(r.dong) + '</td>' +
        '<td class="num">' + esc(r.ho) + '</td>' +
        '<td>' + esc(r.name) + '</td>' +
        '<td class="num">' + esc(r.birth) + '</td>' +
        '<td>' + stepSelect(r.step, o.i) +
          ' <span class="tk-step' + (done ? ' done' : '') + '">' +
          (done ? '완료' : '진행') + '</span></td>' +
        '<td class="num">' + esc(r.at || '') + '</td>' +
        '<td><input type="text" class="jsMemo" data-i="' + o.i + '" value="' + esc(r.memo || '') +
          '" style="width:100%;font:inherit;font-size:13px;padding:4px 7px;border:1px solid var(--line);border-radius:5px"></td>' +
        '</tr>';
    }).join('');
    drawStats();
    drawBulk();
  }

  function drawBulk() {
    var n = Object.keys(sel).filter(function (k) { return sel[k]; }).length;
    $('bulkBar').hidden = n === 0;
    $('selCount').textContent = n + '개 세대 선택됨';
  }

  /* ── 엑셀 읽기 ─────────────────────────────── */

  function openImport() {
    $('fileXlsx').value = '';
    $('fileXlsx').click();
  }

  function readXlsx(file) {
    var fr = new FileReader();
    fr.onload = function (e) {
      var wb;
      try {
        wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array', cellDates: true });
      } catch (err) {
        msg('msg', '엑셀을 읽지 못했습니다. 파일이 손상되었거나 지원하지 않는 형식입니다.', 'err');
        return;
      }
      var ws = wb.Sheets[wb.SheetNames[0]];
      var aoa = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false, defval: '' });
      if (aoa.length < 2) {
        msg('msg', '읽을 자료가 없습니다. 첫 줄은 제목, 둘째 줄부터 세대 자료여야 합니다.', 'err');
        return;
      }
      pending = { head: aoa[0].map(function (h) { return String(h).trim(); }), body: aoa.slice(1),
                  name: file.name.replace(/\.(xlsx|xls|csv)$/i, '') };
      showMap();
    };
    fr.readAsArrayBuffer(file);
  }

  function savedMap(key) {
    try { return JSON.parse(localStorage.getItem(MAPKEY) || '{}')[key] || null; }
    catch (e) { return null; }
  }

  function rememberMap(key, map) {
    try {
      var all = JSON.parse(localStorage.getItem(MAPKEY) || '{}');
      all[key] = map;
      localStorage.setItem(MAPKEY, JSON.stringify(all));
    } catch (e) { /* 사생활 보호 모드면 기억만 못 할 뿐, 작업은 계속된다 */ }
  }

  /** 제목 줄에서 뜻이 통하는 열을 먼저 찍어 준다. */
  function guess(head, k) {
    var hints = {
      dong:  ['동'], ho: ['호'],
      name:  ['이름', '성명', '세대주', '소유자'],
      birth: ['생년', '생일', '주민'],
      step:  ['단계', '진행', '상태'],
      memo:  ['메모', '비고', '특이']
    }[k] || [];
    for (var i = 0; i < head.length; i++) {
      for (var j = 0; j < hints.length; j++) {
        if (head[i].indexOf(hints[j]) >= 0) return i;
      }
    }
    return -1;
  }

  function showMap() {
    var key = cur() || pending.name;
    var prev = savedMap(key);
    $('mapFields').innerHTML = FIELDS.map(function (f) {
      var pick = prev && prev[f.k] != null ? prev[f.k] : guess(pending.head, f.k);
      var opts = '<option value="-1">— 사용 안 함 —</option>' +
        pending.head.map(function (h, i) {
          return '<option value="' + i + '"' + (i === pick ? ' selected' : '') + '>' +
            esc(h || ('(' + (i + 1) + '번째 열)')) + '</option>';
        }).join('');
      return '<label for="m_' + f.k + '">' + f.t + (f.need ? ' *' : '') + '</label>' +
        '<select id="m_' + f.k + '">' + opts + '</select>';
    }).join('');
    $('mapPrev').innerHTML = '읽어들일 줄 <code>' + pending.body.length + '</code>개 · ' +
      '대상 단지 <code>' + esc(key) + '</code>';
    msg('mapMsg', '');
    $('mapModal').classList.add('on');
  }

  function applyMap() {
    var map = {}, miss = [];
    FIELDS.forEach(function (f) {
      var v = parseInt($('m_' + f.k).value, 10);
      map[f.k] = v;
      if (f.need && v < 0) miss.push(f.t);
    });
    if (miss.length) {
      msg('mapMsg', miss.join(', ') + ' 열을 지정해 주십시오. 이 항목이 없으면 세대를 구분할 수 없습니다.', 'err');
      return;
    }
    var key = cur() || pending.name;
    var out = [], skipped = 0;
    pending.body.forEach(function (row) {
      var dong = digits(row[map.dong]), ho = digits(row[map.ho]);
      var name = String(row[map.name] == null ? '' : row[map.name]).trim();
      if (!dong || !ho || !name) { skipped++; return; }
      var step = map.step >= 0 ? String(row[map.step]).trim() : '';
      out.push({
        dong: dong, ho: ho, name: name,
        birth: map.birth >= 0 ? birth6(row[map.birth]) : '',
        step: STEPS.indexOf(step) >= 0 ? step : STEPS[0],
        memo: map.memo >= 0 ? String(row[map.memo] || '').trim() : '',
        at: today()
      });
    });
    if (!out.length) {
      msg('mapMsg', '읽어들인 세대가 없습니다. 열을 잘못 지정했는지 확인해 주십시오.', 'err');
      return;
    }
    rememberMap(key, map);

    // 이미 있는 단지면 동·호로 맞춰 갱신하고, 없던 세대만 새로 넣는다.
    var before = rowsOf(key).length;
    var idx = {}, merged = rowsOf(key).slice();
    merged.forEach(function (r, i) { idx[r.dong + '-' + r.ho] = i; });
    var added = 0, updated = 0;
    out.forEach(function (r) {
      var k = r.dong + '-' + r.ho;
      if (idx[k] != null) {
        var t = merged[idx[k]];
        t.name = r.name;
        if (r.birth) t.birth = r.birth;
        if (r.memo) t.memo = r.memo;
        updated++;
      } else {
        merged.push(r); idx[k] = merged.length - 1; added++;
      }
    });
    merged.sort(function (a, b) {
      return (Number(a.dong) - Number(b.dong)) || (Number(a.ho) - Number(b.ho));
    });
    data[key] = merged;

    sel = {}; filterStep = null;
    $('mapModal').classList.remove('on');
    drawComplexes(key);
    $('complex').value = key;
    draw();
    msg('msg', '읽어들였습니다. 새 세대 ' + added + '개, 갱신 ' + updated + '개' +
      (skipped ? ', 건너뜀 ' + skipped + '줄(동·호·이름이 비어 있음)' : '') +
      (before ? ' · 기존 ' + before + '세대에 합쳤습니다.' : ''), 'ok');
    pending = null;
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
    if (!complexes().length) { msg('msg', '저장할 자료가 없습니다.', 'err'); return; }
    var pw = prompt('저장할 파일의 비밀번호를 정하십시오.\n이 비밀번호가 없으면 파일을 다시 열 수 없습니다.');
    if (!pw) return;
    if (pw.length < 8) { msg('msg', '비밀번호는 8자 이상으로 정해 주십시오.', 'err'); return; }
    var pw2 = prompt('확인을 위해 한 번 더 입력하십시오.');
    if (pw2 !== pw) { msg('msg', '두 번 입력한 비밀번호가 다릅니다.', 'err'); return; }

    var salt = crypto.getRandomValues(new Uint8Array(16));
    var iv = crypto.getRandomValues(new Uint8Array(12));
    deriveKey(pw, salt).then(function (key) {
      return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key,
        enc.encode(JSON.stringify({ v: 1, savedAt: today(), data: data })));
    }).then(function (buf) {
      var body = new Uint8Array(buf);
      var out = new Uint8Array(16 + 12 + body.length);
      out.set(salt, 0); out.set(iv, 16); out.set(body, 28);
      download(new Blob([out], { type: 'application/octet-stream' }),
        '등기진행_' + today() + '.jlreg');
      msg('msg', '암호화 저장본을 내려받았습니다. 파일과 비밀번호는 따로 보관하십시오.', 'ok');
    }).catch(function () {
      msg('msg', '암호화에 실패했습니다. 브라우저를 최신판으로 올린 뒤 다시 시도해 주십시오.', 'err');
    });
  }

  function openEncrypted(file) {
    var pw = prompt('저장할 때 정한 비밀번호를 입력하십시오.');
    if (!pw) return;
    var fr = new FileReader();
    fr.onload = function (e) {
      var raw = new Uint8Array(e.target.result);
      if (raw.length < 29) { msg('msg', '저장본 형식이 아닙니다.', 'err'); return; }
      var salt = raw.slice(0, 16), iv = raw.slice(16, 28), body = raw.slice(28);
      deriveKey(pw, salt).then(function (key) {
        return crypto.subtle.decrypt({ name: 'AES-GCM', iv: iv }, key, body);
      }).then(function (buf) {
        var obj = JSON.parse(dec.decode(buf));
        data = obj.data || {};
        sel = {}; filterStep = null;
        drawComplexes();
        draw();
        msg('msg', (obj.savedAt || '') + ' 저장본을 열었습니다. 단지 ' + complexes().length + '개.', 'ok');
      }).catch(function () {
        msg('msg', '열지 못했습니다. 비밀번호가 다르거나 파일이 손상되었습니다.', 'err');
      });
    };
    fr.readAsArrayBuffer(file);
  }

  /* ── 서버 업로드본 ─────────────────────────── */

  /* 서버로 올릴 때는 생년월일을 그대로 두지 않는다.
     조회는 "맞는지 확인"만 하면 되므로 단방향 해시로 충분하다.
     서버가 털려도 생년월일 원본은 나오지 않는다. */
  function sha256hex(text) {
    return crypto.subtle.digest('SHA-256', enc.encode(text)).then(function (buf) {
      return Array.prototype.map.call(new Uint8Array(buf), function (b) {
        return b.toString(16).padStart(2, '0');
      }).join('');
    });
  }

  function exportForServer() {
    var name = cur();
    var rows = rowsOf(name);
    if (!rows.length) { msg('msg', '내보낼 세대가 없습니다.', 'err'); return; }
    var salt = prompt(
      '서버와 맞출 확인용 문구를 입력하십시오.\n' +
      '서버 설정(config.php)의 VERIFY_SALT 와 똑같아야 조회가 됩니다.');
    if (!salt) return;

    Promise.all(rows.map(function (r) {
      return sha256hex(salt + '|' + r.name + '|' + r.birth).then(function (h) {
        return { dong: r.dong, ho: r.ho, vhash: h, step: r.step, at: r.at || today(), memo: r.memo || '' };
      });
    })).then(function (list) {
      var out = { complex: name, steps: STEPS, exportedAt: today(), households: list };
      download(new Blob([JSON.stringify(out, null, 1)], { type: 'application/json' }),
        'upload_' + name.replace(/\s+/g, '_') + '_' + today() + '.json');
      msg('msg', '서버 업로드본을 내려받았습니다. 이름과 생년월일은 해시로만 들어 있어 원본이 나오지 않습니다.', 'ok');
    });
  }

  /* ── 이어 붙이기 ───────────────────────────── */

  function init() {
    $('bulkStep').innerHTML = STEPS.map(function (s) { return '<option>' + esc(s) + '</option>'; }).join('');

    $('btnImport').addEventListener('click', function () {
      if (!complexes().length && !cur()) {
        var n = prompt('단지 이름을 입력하십시오.');
        if (!n) return;
        data[n] = []; drawComplexes(n); $('complex').value = n;
      }
      openImport();
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

    $('mapOk').addEventListener('click', applyMap);
    $('mapCancel').addEventListener('click', function () {
      $('mapModal').classList.remove('on'); pending = null;
    });

    $('complex').addEventListener('change', function () { sel = {}; filterStep = null; draw(); });
    $('q').addEventListener('input', draw);

    $('stat').addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      filterStep = b.dataset.step || null;
      draw();
    });

    $('rows').addEventListener('change', function (e) {
      var t = e.target, i = parseInt(t.dataset.i, 10);
      if (t.classList.contains('jsStep')) {
        var r = rowsOf(cur())[i];
        r.step = t.value; r.at = today();
        draw();
      } else if (t.classList.contains('jsSel')) {
        sel[i] = t.checked; drawBulk();
      }
    });
    $('rows').addEventListener('input', function (e) {
      if (!e.target.classList.contains('jsMemo')) return;
      var i = parseInt(e.target.dataset.i, 10);
      rowsOf(cur())[i].memo = e.target.value;
    });

    $('chkAll').addEventListener('change', function () {
      var on = this.checked;
      visibleRows().forEach(function (o) { sel[o.i] = on; });
      draw();
    });
    $('btnClearSel').addEventListener('click', function () { sel = {}; draw(); });
    $('btnBulk').addEventListener('click', function () {
      var step = $('bulkStep').value, rows = rowsOf(cur()), n = 0;
      Object.keys(sel).forEach(function (k) {
        if (!sel[k]) return;
        rows[k].step = step; rows[k].at = today(); n++;
      });
      sel = {}; draw();
      msg('msg', n + '개 세대를 "' + step + '" 단계로 바꿨습니다.', 'ok');
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

  // 테스트용 — 콘솔에서 표본 자료를 넣어 화면을 확인할 때 쓴다.
  window.__jltrackSeed = function (name, list) {
    data[name] = list; drawComplexes(name); $('complex').value = name; draw();
  };
})();
