/* 등기업무 시스템 — 공통
 *
 * 옛 조회 시스템(jllawfirm.kr/admin)의 일곱 메뉴를 한 화면에 옮긴다.
 *   기본 정보 · 인적사항 · 등기진행 · 설문 · 등기접수 · 위임장 · 통계
 *
 * 가장 중요한 원칙.
 * 세대주 이름·생년월일·연락처·권리증 주소·환불 계좌는 전부 개인정보다.
 * 홈페이지 저장소(GitHub)에 절대 올리지 않는다. 이 화면은 GitHub API 를 쓰지 않는다.
 * 자료는 브라우저 안에만 있고, 저장은 비밀번호로 암호화한 파일 내려받기다.
 * 서버가 생기면 조회에 필요한 최소한만 해시로 바꿔 올린다.
 *
 * 모듈은 JL.mod(name, {title, render}) 로 등록한다. 파일 하나에 메뉴 하나다.
 */
(function (W) {
  'use strict';

  var JL = W.JL = W.JL || {};

  /* ── 여덟 단계 — 엑셀 제목 줄과 같은 순서 ───────── */
  JL.STEPS = [
    '서류수령', '취득세신고', '등기비용통보', '등기비입금확인',
    '건설사 등기서류수령', '등기소서류접수', '등기완료', '권리증교부'
  ];
  JL.NOT_YET = '접수 전';
  JL.DONE_FROM = 6;

  /* ── 자료 한 벌 ────────────────────────────── */
  function blank() {
    return {
      v: 3,
      savedAt: '',
      settings: { firm: '법무법인 제이엘', tel: '1899-4252', sender: '' },
      complexes: {},   // { 단지명: { name, open, units: { '동-호': 세대 } } }
      surveys: [],     // [{ id, complex, title, org, body, questions, createdAt, responses }]
      intake: [],      // [{ id, complex, dong, ho, name, phone, kind, status, note, at }]
      sms: { templates: [], log: [] }
    };
  }
  JL.db = blank();
  JL.dirty = false;

  JL.touch = function () {
    JL.dirty = true;
    JL.ui.saveState();
  };

  /* ── 거들기 ───────────────────────────────── */
  JL.$ = function (id) { return document.getElementById(id); };

  JL.esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  JL.digits = function (v) { return String(v == null ? '' : v).replace(/[^0-9]/g, ''); };

  JL.today = function () {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') +
      '-' + String(d.getDate()).padStart(2, '0');
  };

  JL.now = function () {
    var d = new Date();
    return JL.today() + ' ' + String(d.getHours()).padStart(2, '0') + ':' +
      String(d.getMinutes()).padStart(2, '0');
  };

  JL.won = function (v) {
    var n = Number(v);
    if (!isFinite(n)) return '';
    return n.toLocaleString('ko-KR');
  };

  JL.uid = function () {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  };

  /** 엑셀 오류값(#REF! #N/A 등)은 빈칸으로 본다. */
  JL.clean = function (v) {
    var s = String(v == null ? '' : v).trim();
    return /^#(REF|N\/A|VALUE|DIV|NAME|NULL|NUM)/i.test(s) ? '' : s;
  };

  JL.birth6 = function (v) {
    if (v instanceof Date) {
      return String(v.getFullYear()).slice(2) +
        String(v.getMonth() + 1).padStart(2, '0') + String(v.getDate()).padStart(2, '0');
    }
    var s = JL.digits(JL.clean(v));
    if (s.length >= 13) return s.slice(0, 6);
    if (s.length === 8) return s.slice(2);
    return s.slice(0, 6);
  };

  JL.dateOnly = function (v) {
    if (v instanceof Date) {
      if (v.getFullYear() < 1990) return '';     // 엑셀이 빈 날짜를 1899년으로 준다
      return v.getFullYear() + '-' + String(v.getMonth() + 1).padStart(2, '0') +
        '-' + String(v.getDate()).padStart(2, '0');
    }
    var s = JL.clean(v);
    var m = s.match(/(\d{2,4})\D+(\d{1,2})\D+(\d{1,2})/);
    if (!m) return '';
    var y = m[1].length === 2 ? '20' + m[1] : m[1];
    if (Number(y) < 1990) return '';
    return y + '-' + m[2].padStart(2, '0') + '-' + m[3].padStart(2, '0');
  };

  /** 휴대폰 번호를 010-0000-0000 꼴로. 알아볼 수 없으면 빈칸. */
  JL.phone = function (v) {
    var s = JL.digits(JL.clean(v));
    if (s.length === 10 && s.indexOf('01') === 0) s = s.slice(0, 3) + '-' + s.slice(3, 6) + '-' + s.slice(6);
    else if (s.length === 11) s = s.slice(0, 3) + '-' + s.slice(3, 7) + '-' + s.slice(7);
    else return '';
    return s;
  };

  JL.download = function (blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
  };

  /** CSV 내려받기. 엑셀이 한글을 깨뜨리지 않게 BOM 을 붙인다. */
  JL.csv = function (rows, filename) {
    var body = rows.map(function (r) {
      return r.map(function (c) {
        var s = String(c == null ? '' : c);
        return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
      }).join(',');
    }).join(String.fromCharCode(13, 10));
    JL.download(new Blob([String.fromCharCode(65279) + body], { type: 'text/csv' }), filename);
  };

  /** CSV 한 줄 나누기. 따옴표 안의 쉼표를 지킨다. */
  JL.splitCsv = function (line) {
    var out = [], cur = '', q = false;
    for (var i = 0; i < line.length; i++) {
      var c = line[i];
      if (q) {
        if (c === '"' && line[i + 1] === '"') { cur += '"'; i++; }
        else if (c === '"') q = false;
        else cur += c;
      } else if (c === '"') q = true;
      else if (c === ',') { out.push(cur); cur = ''; }
      else cur += c;
    }
    out.push(cur);
    return out.map(function (v) { return v.trim(); });
  };

  JL.readCsvLines = function (text) {
    if (text.charCodeAt(0) === 65279) text = text.slice(1);
    var LF = String.fromCharCode(10), CR = String.fromCharCode(13);
    return text.split(LF).map(function (l) { return l.split(CR).join(''); })
      .filter(function (l) { return l.trim(); });
  };

  JL.pickFile = function (accept, cb) {
    var inp = document.createElement('input');
    inp.type = 'file'; inp.accept = accept; inp.hidden = true;
    inp.addEventListener('change', function () {
      if (inp.files[0]) cb(inp.files[0]);
      document.body.removeChild(inp);
    });
    document.body.appendChild(inp);
    inp.click();
  };

  /* ── 단지·세대 ─────────────────────────────── */
  JL.complexNames = function () {
    return Object.keys(JL.db.complexes).sort(function (a, b) { return a.localeCompare(b, 'ko'); });
  };

  JL.complex = function (name) {
    if (!JL.db.complexes[name]) {
      JL.db.complexes[name] = { name: name, open: true, units: {} };
    }
    return JL.db.complexes[name];
  };

  JL.units = function (name) {
    var c = JL.db.complexes[name];
    if (!c) return [];
    return Object.keys(c.units).map(function (k) { return c.units[k]; })
      .sort(function (a, b) {
        return (Number(a.dong) - Number(b.dong)) || (Number(a.ho) - Number(b.ho));
      });
  };

  JL.unitKey = function (dong, ho) { return JL.digits(dong) + '-' + JL.digits(ho); };

  /** 새 세대 한 벌. 모든 모듈이 같은 모양을 쓴다. */
  JL.newUnit = function (dong, ho) {
    return {
      dong: JL.digits(dong), ho: JL.digits(ho),
      owners: [],                           // [{ name, birth, phone }] 공동명의는 둘
      step: JL.NOT_YET, stepAt: '',
      cost: { total: 0, paid: 0, diff: 0, paidAt: '', items: [] },   // items: [{k:'취득세', v:0}, ...]
      lack: '',                             // 미비서류
      cert: { addr: '', sentAt: '' },       // 권리증 수령 주소
      refund: { bank: '', account: '', status: '' },   // 채권 환불
      poa: null,                            // { at, joint }
      memo: ''                              // 고객에게 보이는 안내
    };
  };

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

  JL.save = function (pw) {
    JL.db.savedAt = JL.now();
    var salt = crypto.getRandomValues(new Uint8Array(16));
    var iv = crypto.getRandomValues(new Uint8Array(12));
    return deriveKey(pw, salt).then(function (key) {
      return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key,
        enc.encode(JSON.stringify(JL.db)));
    }).then(function (buf) {
      var body = new Uint8Array(buf), out = new Uint8Array(28 + body.length);
      out.set(salt, 0); out.set(iv, 16); out.set(body, 28);
      JL.download(new Blob([out], { type: 'application/octet-stream' }),
        '등기업무_' + JL.today() + '.jlreg');
      JL.dirty = false;
    });
  };

  JL.open = function (file, pw) {
    return file.arrayBuffer().then(function (ab) {
      var raw = new Uint8Array(ab);
      if (raw.length < 29) throw new Error('형식');
      return deriveKey(pw, raw.slice(0, 16)).then(function (key) {
        return crypto.subtle.decrypt({ name: 'AES-GCM', iv: raw.slice(16, 28) }, key, raw.slice(28));
      });
    }).then(function (buf) {
      var obj = JSON.parse(dec.decode(buf));
      JL.db = migrate(obj);
      JL.dirty = false;
      return JL.db;
    });
  };

  /** 앞서 만든 등기 진행 관리(v1·v2) 저장본도 열리게 한다. */
  function migrate(obj) {
    if (obj && obj.v === 3) {
      var b = blank();
      Object.keys(b).forEach(function (k) { if (obj[k] == null) obj[k] = b[k]; });
      return obj;
    }
    var db = blank();
    var old = (obj && obj.data) || {};
    Object.keys(old).forEach(function (name) {
      var c = { name: name, open: true, units: {} };
      db.complexes[name] = c;
      (old[name] || []).forEach(function (r) {
        var u = JL.newUnit(r.dong, r.ho);
        if (r.name) u.owners.push({ name: r.name, birth: r.birth || '', phone: '' });
        if (r.name2) u.owners.push({ name: r.name2, birth: r.birth2 || '', phone: '' });
        u.step = r.step || JL.NOT_YET; u.stepAt = r.at || '';
        u.cost = { total: r.total || 0, paid: r.paid || 0, diff: r.diff || 0, paidAt: '' };
        u.lack = r.lack || ''; u.cert.sentAt = r.sentAt || '';
        u.poa = r.poaAt ? { at: r.poaAt, joint: !!r.poaJoint } : null;
        u.memo = r.memo || '';
        c.units[JL.unitKey(u.dong, u.ho)] = u;
      });
    });
    return db;
  }

  /* ── 해시 ──────────────────────────────────── */
  JL.sha256 = function (text) {
    return crypto.subtle.digest('SHA-256', enc.encode(text)).then(function (buf) {
      return Array.prototype.map.call(new Uint8Array(buf), function (b) {
        return b.toString(16).padStart(2, '0');
      }).join('');
    });
  };

  /* ── 모듈 ──────────────────────────────────── */
  JL.mods = {};
  JL.order = [];
  JL.mod = function (id, def) { JL.mods[id] = def; JL.order.push(id); };

})(window);
