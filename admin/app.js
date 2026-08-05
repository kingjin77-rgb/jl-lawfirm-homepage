/* 콘텐츠 관리 — data/magazine.json 편집 후 GitHub Contents API로 커밋
   서버가 필요 없다. 토큰은 localStorage 에만 저장되며 GitHub 외 어디로도 전송되지 않는다.
*/
(function () {
  'use strict';

  var LS_GH = 'jladmin.gh';
  var DATA_PATH = 'data/magazine.json';

  var data = { updatedAt: '', issues: [], reports: [] };
  var dirty = false;

  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  /* ---------- 로그 ---------- */
  function log(msg, kind) {
    var el = $('log');
    var line = document.createElement('div');
    line.className = kind || 'dim';
    line.textContent = msg;
    if (el.firstChild && el.firstChild.className === 'dim' && el.children.length === 1 &&
        el.firstChild.textContent === '대기 중…') el.innerHTML = '';
    el.appendChild(line);
    el.scrollTop = el.scrollHeight;
  }

  /* ---------- GitHub 설정 ---------- */
  function ghGet() {
    try { return JSON.parse(localStorage.getItem(LS_GH) || 'null'); }
    catch (e) { return null; }
  }
  function ghSet(v) {
    if (v) localStorage.setItem(LS_GH, JSON.stringify(v));
    else localStorage.removeItem(LS_GH);
    paintState();
  }
  function paintState() {
    var gh = ghGet();
    var el = $('state');
    if (gh && gh.repo && gh.token) {
      el.textContent = '연결됨 · ' + gh.repo + ' (' + (gh.branch || 'main') + ')';
      el.className = 'adm__state ok';
    } else {
      el.textContent = 'GitHub 미연결';
      el.className = 'adm__state';
    }
  }

  $('btnSaveGh').addEventListener('click', function () {
    var repo = $('ghRepo').value.trim();
    var token = $('ghToken').value.trim();
    var branch = $('ghBranch').value.trim() || 'main';
    if (!repo || !token) { log('저장소와 토큰을 모두 입력하세요.', 'bad'); return; }
    ghSet({ repo: repo, token: token, branch: branch });
    log('연결 정보를 저장했습니다 — ' + repo + ' (' + branch + ')', 'ok');
  });

  $('btnClearGh').addEventListener('click', function () {
    ghSet(null);
    $('ghToken').value = '';
    log('연결을 해제했습니다. 토큰을 브라우저에서 지웠습니다.', 'ok');
  });

  $('btnTest').addEventListener('click', async function () {
    var gh = ghGet();
    if (!gh) { log('먼저 연결 정보를 저장하세요.', 'bad'); return; }
    log('연결 확인 중…');
    try {
      var r = await fetch('https://api.github.com/repos/' + gh.repo, {
        headers: { Authorization: 'Bearer ' + gh.token, Accept: 'application/vnd.github+json' }
      });
      if (!r.ok) throw new Error(r.status + ' ' + (await r.text()).slice(0, 120));
      var j = await r.json();
      log('확인 완료 — ' + j.full_name + ' / 기본 브랜치 ' + j.default_branch +
          ' / 쓰기권한 ' + (j.permissions && j.permissions.push ? '있음' : '없음'),
          j.permissions && j.permissions.push ? 'ok' : 'bad');
    } catch (e) {
      log('확인 실패: ' + e.message, 'bad');
    }
  });

  /* ---------- 데이터 로드 ---------- */
  async function load() {
    try {
      var r = await fetch('../data/magazine.json?cb=' + Date.now(), { cache: 'no-cache' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      data = await r.json();
      data.issues = data.issues || [];
      data.reports = data.reports || [];
      dirty = false;
      renderReports();
      renderIssues();
      log('현재 내용을 불러왔습니다 — 발행호 ' + data.issues.length +
          '건, 검토보고서 ' + data.reports.length + '건', 'ok');
    } catch (e) {
      log('불러오기 실패: ' + e.message, 'bad');
    }
  }

  /* ---------- 검토보고서 ---------- */
  var CATS = [['공고검토', '분양공고 검토'], ['민간임대', '민간임대'], ['하자', '하자 · 개선요구']];

  function renderReports() {
    var wrap = $('reportList');
    wrap.innerHTML = data.reports.map(function (r, i) {
      return '' +
      '<div class="item" data-ri="' + i + '">' +
        '<div class="item__top">' +
          '<span class="idx">' + String(i + 1).padStart(2, '0') + '</span>' +
          '<span class="cat">' + esc(r.cat) + '</span>' +
          '<span class="ttl">' + esc(r.title || '(제목 없음)') + '</span>' +
          '<button class="mini" data-act="toggle">편집</button>' +
          '<button class="mini" data-act="up">↑</button>' +
          '<button class="mini" data-act="down">↓</button>' +
          '<button class="mini" data-act="del">삭제</button>' +
        '</div>' +
        '<div class="item__body">' +
          '<div class="row c2">' +
            '<div><label class="f">분류</label><select data-k="cat">' +
              CATS.map(function (c) {
                return '<option value="' + c[0] + '"' + (r.cat === c[0] ? ' selected' : '') + '>' + c[1] + '</option>';
              }).join('') + '</select></div>' +
            '<div><label class="f">링크 (비우면 “준비 중”)</label>' +
              '<input type="text" data-k="link" value="' + esc(r.link || '') + '" placeholder="docs/report.pdf"></div>' +
          '</div>' +
          '<div class="row"><div><label class="f">제목</label>' +
            '<input type="text" data-k="title" value="' + esc(r.title || '') + '"></div></div>' +
          '<div class="row"><div><label class="f">설명</label>' +
            '<textarea data-k="desc">' + esc(r.desc || '') + '</textarea></div></div>' +
          '<div class="row c2">' +
            '<div><label class="f">태그 (쉼표 구분)</label>' +
              '<input type="text" data-k="tags" value="' + esc((r.tags || []).join(', ')) + '"></div>' +
            '<div><label class="f">썸네일 배경 (CSS)</label>' +
              '<input type="text" data-k="gradient" value="' + esc(r.gradient || '') + '"></div>' +
          '</div>' +
        '</div>' +
      '</div>';
    }).join('') || '<p class="hint" style="padding:16px 0">등록된 보고서가 없습니다.</p>';
  }

  $('reportList').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-act]');
    if (!btn) return;
    var item = btn.closest('.item');
    var i = parseInt(item.dataset.ri, 10);
    var act = btn.dataset.act;

    if (act === 'toggle') { item.classList.toggle('open'); return; }
    if (act === 'del') {
      if (!confirm('“' + (data.reports[i].title || '제목 없음') + '” 항목을 지울까요?')) return;
      data.reports.splice(i, 1);
    }
    if (act === 'up' && i > 0) {
      data.reports.splice(i - 1, 0, data.reports.splice(i, 1)[0]);
    }
    if (act === 'down' && i < data.reports.length - 1) {
      data.reports.splice(i + 1, 0, data.reports.splice(i, 1)[0]);
    }
    dirty = true;
    renderReports();
  });

  $('reportList').addEventListener('input', function (e) {
    var f = e.target.closest('[data-k]');
    if (!f) return;
    var i = parseInt(f.closest('.item').dataset.ri, 10);
    var k = f.dataset.k;
    var v = f.value;
    data.reports[i][k] = (k === 'tags')
      ? v.split(',').map(function (x) { return x.trim(); }).filter(Boolean)
      : v;
    dirty = true;
    if (k === 'title' || k === 'cat') {
      var top = f.closest('.item').querySelector('.ttl');
      var cat = f.closest('.item').querySelector('.cat');
      if (top) top.textContent = data.reports[i].title || '(제목 없음)';
      if (cat) cat.textContent = data.reports[i].cat;
    }
  });

  $('btnAddReport').addEventListener('click', function () {
    data.reports.unshift({
      cat: '공고검토', title: '', desc: '', tags: [], link: '',
      gradient: 'linear-gradient(150deg,#1b2b57,#4b6bab)'
    });
    dirty = true;
    renderReports();
    var first = $('reportList').querySelector('.item');
    if (first) first.classList.add('open');
  });

  /* ---------- 발행호 ---------- */
  function renderIssues() {
    var wrap = $('issueList');
    wrap.innerHTML = data.issues.map(function (it, i) {
      return '' +
      '<div class="item" data-ii="' + i + '">' +
        '<div class="item__top">' +
          '<span class="idx">' + String(it.no).padStart(2, '0') + '</span>' +
          '<span class="ttl">' + esc((it.title || '').replace(/<br\s*\/?>/gi, ' ')) + '</span>' +
          '<button class="mini" data-act="toggle">편집</button>' +
        '</div>' +
        '<div class="item__body">' +
          '<div class="row c3">' +
            '<div><label class="f">호수</label><input type="text" data-k="no" value="' + esc(it.no) + '"></div>' +
            '<div><label class="f">라벨</label><input type="text" data-k="label" value="' + esc(it.label || '') + '"></div>' +
            '<div><label class="f">본문 링크</label><input type="text" data-k="link" value="' + esc(it.link || '') + '"></div>' +
          '</div>' +
          '<div class="row"><div><label class="f">제목 (&lt;br&gt; 사용 가능)</label>' +
            '<input type="text" data-k="title" value="' + esc(it.title || '') + '"></div></div>' +
          '<div class="row"><div><label class="f">설명</label>' +
            '<textarea data-k="desc">' + esc(it.desc || '') + '</textarea></div></div>' +
          '<div class="row c2">' +
            '<div><label class="f">표지 이미지 경로</label><input type="text" data-k="cover" value="' + esc(it.cover || '') + '"></div>' +
            '<div><label class="f">태그 (쉼표 구분)</label><input type="text" data-k="tags" value="' + esc((it.tags || []).join(', ')) + '"></div>' +
          '</div>' +
          '<p class="hint" style="margin-top:14px">수록 기사 ' + (it.contents || []).length +
            '건은 JSON에서 직접 편집합니다.</p>' +
        '</div>' +
      '</div>';
    }).join('') || '<p class="hint" style="padding:16px 0">등록된 발행호가 없습니다.</p>';
  }

  $('issueList').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-act="toggle"]');
    if (btn) btn.closest('.item').classList.toggle('open');
  });

  $('issueList').addEventListener('input', function (e) {
    var f = e.target.closest('[data-k]');
    if (!f) return;
    var i = parseInt(f.closest('.item').dataset.ii, 10);
    var k = f.dataset.k;
    var v = f.value;
    if (k === 'tags') v = v.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
    if (k === 'no') v = parseInt(v, 10) || 0;
    data.issues[i][k] = v;
    dirty = true;
  });

  /* ---------- 저장 ---------- */
  function serialize() {
    data.updatedAt = new Date().toISOString().slice(0, 10);
    return JSON.stringify(data, null, 2) + '\n';
  }

  function b64(str) {
    var bytes = new TextEncoder().encode(str);
    var bin = '';
    bytes.forEach(function (b) { bin += String.fromCharCode(b); });
    return btoa(bin);
  }

  $('btnDownload').addEventListener('click', function () {
    var blob = new Blob([serialize()], { type: 'application/json' });
    var u = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = u; a.download = 'magazine.json'; a.click();
    setTimeout(function () { URL.revokeObjectURL(u); }, 2000);
    log('magazine.json 을 내려받았습니다. data/ 에 덮어쓰세요.', 'ok');
  });

  $('btnReload').addEventListener('click', function () {
    if (dirty && !confirm('저장하지 않은 변경이 있습니다. 버리고 다시 불러올까요?')) return;
    load();
  });

  $('btnPublish').addEventListener('click', async function () {
    var gh = ghGet();
    if (!gh) { log('GitHub 연결 정보가 없습니다.', 'bad'); return; }
    if (!confirm(gh.repo + ' (' + (gh.branch || 'main') + ') 에 커밋합니다. 진행할까요?')) return;

    var btn = this;
    btn.disabled = true;
    log('저장 중…');
    try {
      var url = 'https://api.github.com/repos/' + gh.repo + '/contents/' + DATA_PATH;
      var h = { Authorization: 'Bearer ' + gh.token, Accept: 'application/vnd.github+json' };

      var sha = null;
      var g = await fetch(url + '?ref=' + (gh.branch || 'main'), { headers: h });
      if (g.ok) sha = (await g.json()).sha;
      else if (g.status !== 404) throw new Error('조회 실패 ' + g.status);

      var body = {
        message: '매거진 콘텐츠 수정 (관리자 페이지)',
        content: b64(serialize()),
        branch: gh.branch || 'main'
      };
      if (sha) body.sha = sha;

      var r = await fetch(url, {
        method: 'PUT',
        headers: Object.assign({ 'Content-Type': 'application/json' }, h),
        body: JSON.stringify(body)
      });
      if (!r.ok) throw new Error(r.status + ' ' + (await r.text()).slice(0, 160));

      var j = await r.json();
      dirty = false;
      log('저장 완료 — 커밋 ' + (j.commit && j.commit.sha ? j.commit.sha.slice(0, 8) : ''), 'ok');
      log('배포가 자동으로 이어집니다. 반영까지 1~2분 걸릴 수 있습니다.', 'dim');
    } catch (e) {
      log('저장 실패: ' + e.message, 'bad');
    } finally {
      btn.disabled = false;
    }
  });

  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- 초기화 ---------- */
  var gh = ghGet();
  if (gh) {
    $('ghRepo').value = gh.repo || '';
    $('ghBranch').value = gh.branch || 'main';
    $('ghToken').value = gh.token || '';
  } else {
    $('ghRepo').value = 'kingjin77-rgb/jl-lawfirm-homepage';
    $('ghBranch').value = 'main';
  }
  paintState();
  load();
})();
