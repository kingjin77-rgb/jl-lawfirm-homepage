/* 질의응답 관리 — data/qna.json 을 GitHub Contents API 로 커밋한다.
   연결 정보(저장소·토큰)는 콘텐츠 관리자와 공유한다.
*/
(function () {
  'use strict';

  var LS = 'jladmin.gh';
  var PATH = 'data/qna.json';
  var data = null, sha = null;
  var base = '';                 // 불러온 원본(JSON 문자열) — sha 없이 연 경우 저장 전 대조용
  var busy = false;              // 저장 중 중복 클릭 방지

  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  function log(msg, kind) {
    var el = $('log'), line = document.createElement('div');
    line.className = kind || 'dim';
    line.textContent = msg;
    // 처음 자리의 '대기 중…' 한 줄만 지운다. 조건이 느슨하면 직전 안내(dim) 줄까지 지워진다.
    if (el.firstChild && el.firstChild.className === 'dim' && el.children.length === 1 &&
        el.firstChild.textContent === '대기 중…') el.innerHTML = '';
    el.appendChild(line);
    el.scrollTop = el.scrollHeight;
  }

  var dirty = false;

  /* GitHub 오류를 사람 말로 옮긴다 — 'HTTP 409' 만으로는 무엇을 해야 할지 알 수 없다. */
  function ghFail(r) {
    return r.text().then(function (t) {
      var m = '';
      try { m = (JSON.parse(t).message || ''); } catch (e) { m = t.slice(0, 120); }
      if (r.status === 409 || /does not match|sha/i.test(m))
        return '다른 곳에서 이 파일이 먼저 저장되었습니다. [다시 불러오기] 후 '
             + '수정 내용을 다시 입력하고 저장해 주세요.';
      if (r.status === 401) return '토큰이 만료되었거나 잘못되었습니다. 새 토큰으로 다시 연결해 주세요.';
      if (r.status === 403) return '토큰에 이 저장소 쓰기 권한이 없습니다. Contents 쓰기 권한을 확인해 주세요.';
      if (r.status === 404) return '저장소 또는 경로를 찾을 수 없습니다. 저장소 이름을 확인해 주세요.';
      return 'HTTP ' + r.status + (m ? ' — ' + m : '');
    });
  }

  /* 불러오기·sha 조회 실패를 직원이 할 일로 옮긴다 */
  function statusMsg(s) {
    if (s === 401) return '토큰이 만료되었거나 잘못되었습니다. 새 토큰으로 다시 연결해 주세요.';
    if (s === 403) return '토큰에 이 저장소 권한이 없거나 GitHub 요청 한도를 넘었습니다. 토큰의 Contents 권한을 확인해 주세요.';
    if (s === 404) return '저장소 또는 경로를 찾을 수 없습니다. 저장소 이름과 토큰의 저장소 범위를 확인해 주세요.';
    return 'HTTP ' + s;
  }
  // fetch 자체가 실패(오프라인·차단)하면 'Failed to fetch' 만 남는다
  function netMsg(e) {
    return (e instanceof TypeError) ? 'GitHub에 연결할 수 없습니다. 인터넷 연결을 확인해 주세요.' : e.message;
  }
  function decode(b64) { return JSON.parse(decodeURIComponent(escape(atob(b64.replace(/\n/g, ''))))); }

  function gh() {
    try { return JSON.parse(localStorage.getItem(LS) || 'null'); } catch (e) { return null; }
  }

  function paint() {
    var g = gh(), el = $('state');
    if (g && g.repo && g.token) { el.textContent = '연결됨 · ' + g.repo; el.className = 'adm__state ok'; }
    else { el.textContent = 'GitHub 미연결'; el.className = 'adm__state'; }
    // 저장된 토큰은 칸에 다시 채우지 않고 안내문으로만 알린다
    $('ghToken').placeholder = (g && g.token) ? '저장된 토큰 사용 중 (바꿀 때만 새로 입력)' : 'github_pat_...';
    if (g) { $('ghRepo').value = g.repo || ''; $('ghBranch').value = g.branch || 'main'; }
    else { $('ghRepo').value = 'kingjin77-rgb/jl-lawfirm-homepage'; $('ghBranch').value = 'main'; }
  }

  $('btnSaveGh').onclick = function () {
    var prev = gh();
    // 토큰 칸을 비워 두면 저장된 토큰을 그대로 쓴다 (저장소·브랜치만 바꾸는 경우)
    var repo = $('ghRepo').value.trim(), tok = $('ghToken').value.trim() || (prev && prev.token) || '';
    if (!repo || !tok) { log('저장소와 토큰을 모두 입력하세요.', 'bad'); return; }
    localStorage.setItem(LS, JSON.stringify({
      repo: repo, token: tok, branch: $('ghBranch').value.trim() || 'main'
    }));
    $('ghToken').value = '';
    paint();
    log('연결 정보를 저장했습니다.', 'ok');
  };

  $('btnClearGh').onclick = function () {
    localStorage.removeItem(LS); paint(); log('연결을 해제했습니다.', 'ok');
  };

  function catOptions(selected) {
    return data.categories.map(function (c) {
      return '<option value="' + esc(c.key) + '"' +
             (c.key === selected ? ' selected' : '') + '>' + esc(c.label) + '</option>';
    }).join('');
  }

  function catLabel(key) {
    var c = data.categories.filter(function (x) { return x.key === key; })[0];
    return c ? c.label : key;
  }

  function renderList() {
    $('qnaList').innerHTML = data.items.map(function (it, i) {
      return '' +
      '<div class="item" data-i="' + i + '">' +
        '<div class="item__top">' +
          '<span class="idx">' + (i + 1) + '</span>' +
          '<span class="ttl">' + esc(it.q || '(질문 없음)') + '</span>' +
          '<span class="cat">' + esc(catLabel(it.cat)) + '</span>' +
          '<button class="mini" data-act="toggle">펼치기</button>' +
          '<button class="mini" data-act="up">↑</button>' +
          '<button class="mini" data-act="down">↓</button>' +
          '<button class="mini" data-act="del">삭제</button>' +
        '</div>' +
        '<div class="item__body">' +
          '<div class="row c2">' +
            '<div><label class="f">분야</label><select data-k="cat">' + catOptions(it.cat) + '</select></div>' +
            '<div><label class="f">답변일</label><input type="date" data-k="date" value="' + esc(it.date || '') + '"></div>' +
          '</div>' +
          '<div class="row"><div><label class="f">질문</label>' +
            '<textarea data-k="q" style="min-height:70px">' + esc(it.q || '') + '</textarea></div></div>' +
          '<div class="row"><div><label class="f">답변</label>' +
            '<textarea data-k="a" style="min-height:180px">' + esc(it.a || '') + '</textarea></div></div>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  $('qnaList').addEventListener('click', function (e) {
    var b = e.target.closest('button[data-act]');
    if (!b) return;
    var wrap = b.closest('.item');
    var i = parseInt(wrap.dataset.i, 10);
    var act = b.dataset.act;
    if (act === 'toggle') {
      var open = wrap.classList.toggle('open');
      b.textContent = open ? '접기' : '펼치기';
      return;
    }
    if (act === 'del') {
      if (!confirm('이 항목을 지울까요?')) return;
      data.items.splice(i, 1);
    }
    if (act === 'up' && i > 0) data.items.splice(i - 1, 0, data.items.splice(i, 1)[0]);
    if (act === 'down' && i < data.items.length - 1) data.items.splice(i + 1, 0, data.items.splice(i, 1)[0]);
    dirty = true;
    renderList();
  });

  $('qnaList').addEventListener('input', function (e) {
    var el = e.target.closest('[data-k]');
    if (!el) return;
    var i = parseInt(el.closest('.item').dataset.i, 10);
    data.items[i][el.dataset.k] = el.value;
    dirty = true;
    if (el.dataset.k === 'q') {
      var ttl = el.closest('.item').querySelector('.ttl');
      if (ttl) ttl.textContent = el.value || '(질문 없음)';
    }
  });

  $('qnaList').addEventListener('change', function (e) {
    var el = e.target.closest('select[data-k]');
    if (!el) return;
    var i = parseInt(el.closest('.item').dataset.i, 10);
    data.items[i][el.dataset.k] = el.value;
    dirty = true;
    var cat = el.closest('.item').querySelector('.cat');
    if (cat) cat.textContent = catLabel(el.value);
  });

  $('btnAdd').onclick = function () {
    // 목록을 못 불러온 상태에서 누르면 data 가 null 이라 스크립트가 멈춘다
    if (!data || !data.items) {
      log('아직 질의응답 목록을 불러오지 못했습니다. [다시 불러오기]로 목록을 연 뒤 추가해 주세요.', 'bad');
      return;
    }
    var cats = data.categories || [];
    data.items.unshift({
      cat: cats.length ? cats[0].key : '', q: '', a: '',
      date: new Date().toISOString().slice(0, 10)
    });
    dirty = true;
    renderList();
    var first = $('qnaList').querySelector('.item');
    if (first) {
      first.classList.add('open');
      first.querySelector('[data-act="toggle"]').textContent = '접기';
      first.querySelector('textarea').focus();
    }
  };

  function load() {
    var g = gh();
    var headers = { Accept: 'application/vnd.github+json' };
    if (g && g.token) headers.Authorization = 'Bearer ' + g.token;
    var repo = (g && g.repo) || 'kingjin77-rgb/jl-lawfirm-homepage';
    var branch = (g && g.branch) || 'main';

    fetch('https://api.github.com/repos/' + repo + '/contents/' + PATH + '?ref=' + branch, { headers: headers })
      .then(function (r) { if (!r.ok) throw new Error(statusMsg(r.status)); return r.json(); })
      .then(function (j) {
        sha = j.sha;
        data = decode(j.content);
        base = JSON.stringify(data);
        dirty = false;
        renderList();
        log('불러오기 완료 — ' + data.items.length + '건', 'ok');
      })
      .catch(function (e) {
        log('GitHub에서 불러오지 못해 로컬 파일로 시도합니다. (' + netMsg(e) + ')');
        fetch('../data/qna.json?cb=' + Date.now(), { cache: 'no-cache' })
          .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
          .then(function (d) {
            data = d; sha = null; base = JSON.stringify(d); dirty = false; renderList();
            log('로컬 파일 불러옴 — 저장할 때 GitHub 최신본과 대조한 뒤 커밋합니다.', 'ok');
          })
          .catch(function (e2) { log('불러오기 실패: ' + e2.message, 'bad'); });
      });
  }

  /* 저장 직전 sha 확보.
     GitHub 불러오기가 실패해 로컬 파일로 열었으면 sha 가 없다. 그대로 PUT 하면
     422(sha 누락)로 거절되고, [다시 불러오기]도 같은 이유로 또 로컬로 떨어져 끝없이 반복된다.
     그래서 토큰으로 지금 파일을 다시 조회해 sha 를 받는다. 단 GitHub 쪽 내용이
     화면에 연 로컬 파일과 다르면(그사이 다른 직원이 저장) 덮어쓰지 않고 멈춘다. */
  function ensureSha(g) {
    if (sha) return Promise.resolve(sha);
    var url = 'https://api.github.com/repos/' + g.repo + '/contents/' + PATH + '?ref=' + (g.branch || 'main');
    return fetch(url, { headers: { Authorization: 'Bearer ' + g.token, Accept: 'application/vnd.github+json' } })
      .then(function (r) {
        if (r.status === 404) return null;               // 아직 없는 파일 — 새로 만든다
        if (!r.ok) throw new Error(statusMsg(r.status));
        return r.json().then(function (j) {
          if (JSON.stringify(decode(j.content)) !== base)
            throw new Error('GitHub에 있는 최신본이 지금 화면의 내용과 다릅니다. '
                          + '[다시 불러오기]로 최신본을 연 뒤 수정 내용을 다시 입력해 주세요.');
          sha = j.sha;
          return sha;
        });
      });
  }

  $('btnReload').onclick = function () {
    if (dirty && !confirm('저장하지 않은 변경이 있습니다. 버리고 다시 불러올까요?')) return;
    load();
  };

  $('btnPublish').onclick = function () {
    var g = gh();
    if (busy) return;
    if (!g || !g.token) { log('먼저 GitHub 연결 정보를 저장하세요. (1번 칸에 저장소와 토큰 입력)', 'bad'); return; }
    if (!data) { log('불러온 데이터가 없습니다. [다시 불러오기]를 먼저 눌러 주세요.', 'bad'); return; }

    var empty = data.items.filter(function (x) { return !x.q || !x.a; }).length;
    if (empty && !confirm('질문 또는 답변이 비어 있는 항목이 ' + empty + '건 있습니다. 그대로 저장할까요?')) return;

    data.updatedAt = new Date().toISOString().slice(0, 10);
    if (!confirm(g.repo + ' 에 qna.json 을 커밋합니다. 진행할까요?')) return;

    var payload = {
      message: '질의응답 갱신 — ' + data.items.length + '건',
      content: btoa(unescape(encodeURIComponent(JSON.stringify(data, null, 2) + '\n'))),
      branch: g.branch || 'main'
    };

    busy = true;
    log('저장 중…');
    ensureSha(g)
      .then(function (s) {
        if (s) payload.sha = s;
        return fetch('https://api.github.com/repos/' + g.repo + '/contents/' + PATH, {
          method: 'PUT',
          headers: { Authorization: 'Bearer ' + g.token, Accept: 'application/vnd.github+json' },
          body: JSON.stringify(payload)
        });
      })
      .then(function (r) {
        if (!r.ok) return ghFail(r).then(function (m) { throw new Error(m); });
        return r.json();
      })
      .then(function (j) {
        sha = j.content.sha;
        base = JSON.stringify(data);
        dirty = false;
        log('커밋 완료 — ' + j.commit.sha.slice(0, 7), 'ok');
        log('배포가 자동으로 이어집니다. 반영까지 1~2분 걸릴 수 있습니다.', 'dim');
      })
      .catch(function (e) { log('저장 실패: ' + netMsg(e), 'bad'); })
      .then(function () { busy = false; });
  };

  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  paint();
  load();
})();
