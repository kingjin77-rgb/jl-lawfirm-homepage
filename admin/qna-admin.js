/* 질의응답 관리 — data/qna.json 을 GitHub Contents API 로 커밋한다.
   연결 정보(저장소·토큰)는 콘텐츠 관리자와 공유한다.
*/
(function () {
  'use strict';

  var LS = 'jladmin.gh';
  var PATH = 'data/qna.json';
  var data = null, sha = null;

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
    if (el.firstChild && el.firstChild.className === 'dim' && el.children.length === 1) el.innerHTML = '';
    el.appendChild(line);
    el.scrollTop = el.scrollHeight;
  }

  function gh() {
    try { return JSON.parse(localStorage.getItem(LS) || 'null'); } catch (e) { return null; }
  }

  function paint() {
    var g = gh(), el = $('state');
    if (g && g.repo && g.token) { el.textContent = '연결됨 · ' + g.repo; el.className = 'adm__state ok'; }
    else { el.textContent = 'GitHub 미연결'; el.className = 'adm__state'; }
    if (g) { $('ghRepo').value = g.repo || ''; $('ghBranch').value = g.branch || 'main'; }
    else { $('ghRepo').value = 'kingjin77-rgb/jl-lawfirm-homepage'; $('ghBranch').value = 'main'; }
  }

  $('btnSaveGh').onclick = function () {
    var repo = $('ghRepo').value.trim(), tok = $('ghToken').value.trim();
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
    renderList();
  });

  $('qnaList').addEventListener('input', function (e) {
    var el = e.target.closest('[data-k]');
    if (!el) return;
    var i = parseInt(el.closest('.item').dataset.i, 10);
    data.items[i][el.dataset.k] = el.value;
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
    var cat = el.closest('.item').querySelector('.cat');
    if (cat) cat.textContent = catLabel(el.value);
  });

  $('btnAdd').onclick = function () {
    data.items.unshift({
      cat: data.categories[0].key, q: '', a: '',
      date: new Date().toISOString().slice(0, 10)
    });
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
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (j) {
        sha = j.sha;
        data = JSON.parse(decodeURIComponent(escape(atob(j.content.replace(/\n/g, '')))));
        renderList();
        log('불러오기 완료 — ' + data.items.length + '건', 'ok');
      })
      .catch(function (e) {
        log('GitHub에서 불러오지 못해 로컬 파일로 시도합니다. (' + e.message + ')');
        fetch('../data/qna.json', { cache: 'no-cache' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            data = d; sha = null; renderList();
            log('로컬 파일 불러옴 — 저장하려면 GitHub 연결이 필요합니다.', 'ok');
          })
          .catch(function (e2) { log('불러오기 실패: ' + e2.message, 'bad'); });
      });
  }

  $('btnReload').onclick = load;

  $('btnPublish').onclick = function () {
    var g = gh();
    if (!g || !g.token) { log('먼저 GitHub 연결 정보를 저장하세요.', 'bad'); return; }
    if (!data) { log('불러온 데이터가 없습니다.', 'bad'); return; }

    var empty = data.items.filter(function (x) { return !x.q || !x.a; }).length;
    if (empty && !confirm('질문 또는 답변이 비어 있는 항목이 ' + empty + '건 있습니다. 그대로 저장할까요?')) return;

    data.updatedAt = new Date().toISOString().slice(0, 10);
    if (!confirm(g.repo + ' 에 qna.json 을 커밋합니다. 진행할까요?')) return;

    var payload = {
      message: '질의응답 갱신 — ' + data.items.length + '건',
      content: btoa(unescape(encodeURIComponent(JSON.stringify(data, null, 2) + '\n'))),
      branch: g.branch || 'main'
    };
    if (sha) payload.sha = sha;

    fetch('https://api.github.com/repos/' + g.repo + '/contents/' + PATH, {
      method: 'PUT',
      headers: { Authorization: 'Bearer ' + g.token, Accept: 'application/vnd.github+json' },
      body: JSON.stringify(payload)
    })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (j) { sha = j.content.sha; log('커밋 완료 — ' + j.commit.sha.slice(0, 7), 'ok'); })
      .catch(function (e) { log('저장 실패: ' + e.message, 'bad'); });
  };

  paint();
  load();
})();
