/* 질의응답 — data/qna.json

   목록 렌더링(분야 필터 · 검색 · 아코디언)과 질문 접수 폼을 함께 담당한다.
   접수는 상담폼과 같은 원칙 — 외부 서버를 거치지 않고 메일 앱으로 넘긴다.
*/
(function () {
  'use strict';

  var listEl = document.querySelector('[data-qna-list]');
  var TO = 'jllaw2020@naver.com';

  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };
  var nl2br = function (s) { return esc(s).replace(/\n/g, '<br>'); };

  var DATA = null;
  var filter = 'all';
  var keyword = '';

  function label(key) {
    if (!DATA) return key;
    var c = DATA.categories.filter(function (x) { return x.key === key; })[0];
    return c ? c.label : key;
  }

  function render() {
    if (!listEl || !DATA) return;
    var items = DATA.items.filter(function (it) {
      if (filter !== 'all' && it.cat !== filter) return false;
      if (keyword) {
        var hay = (it.q + ' ' + it.a).toLowerCase();
        if (hay.indexOf(keyword.toLowerCase()) < 0) return false;
      }
      return true;
    });

    var cnt = document.querySelector('[data-qna-count]');
    if (cnt) cnt.textContent = items.length;

    if (!items.length) {
      listEl.innerHTML = '<p class="board-empty">해당하는 질문이 없습니다. 아래에서 직접 질문해 주세요.</p>';
      return;
    }

    listEl.innerHTML = items.map(function (it, i) {
      return '' +
      '<article class="qa' + (i === 0 ? ' is-open' : '') + '">' +
        '<button type="button" class="qa__q" aria-expanded="' + (i === 0) + '">' +
          '<span class="qa__cat">' + esc(label(it.cat)) + '</span>' +
          '<span class="qa__title">' + esc(it.q) + '</span>' +
          '<span class="qa__mark" aria-hidden="true"></span>' +
        '</button>' +
        '<div class="qa__a"><div class="qa__inner">' + nl2br(it.a) +
          (it.date ? '<p class="qa__date">답변 ' + esc(it.date) + ' · 법무법인 제이엘</p>' : '') +
        '</div></div>' +
      '</article>';
    }).join('');
  }

  /* 필터 · 검색 */
  var tabsEl = document.querySelector('[data-qna-tabs]');
  if (tabsEl) {
    tabsEl.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-cat]');
      if (!b) return;
      filter = b.dataset.cat;
      tabsEl.querySelectorAll('button').forEach(function (x) {
        x.classList.toggle('is-on', x === b);
      });
      render();
    });
  }
  var searchEl = document.querySelector('[data-qna-search]');
  if (searchEl) {
    searchEl.addEventListener('input', function () { keyword = searchEl.value.trim(); render(); });
  }

  /* 아코디언 */
  if (listEl) {
    listEl.addEventListener('click', function (e) {
      var btn = e.target.closest('.qa__q');
      if (!btn) return;
      var card = btn.closest('.qa');
      var open = card.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
    });
  }

  /* 데이터 */
  fetch('data/qna.json', { cache: 'no-cache' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      DATA = d;
      if (tabsEl) {
        tabsEl.innerHTML = '<button type="button" class="is-on" data-cat="all">전체</button>' +
          d.categories.map(function (c) {
            return '<button type="button" data-cat="' + esc(c.key) + '">' + esc(c.label) + '</button>';
          }).join('');
      }
      var sel = document.querySelector('#qCat');
      if (sel) {
        d.categories.forEach(function (c) {
          var o = document.createElement('option');
          o.value = c.label; o.textContent = c.label;
          sel.appendChild(o);
        });
      }
      render();
    })
    .catch(function () {
      if (listEl) listEl.innerHTML = '<p class="board-empty">질의응답을 불러오지 못했습니다.</p>';
    });

  /* 질문 접수 */
  var form = document.querySelector('[data-qna-form]');
  if (!form) return;

  function val(id) {
    var el = form.querySelector('#' + id);
    return el ? el.value.trim() : '';
  }
  function say(msg, kind) {
    var el = form.querySelector('[data-qna-msg]');
    if (!el) return;
    el.textContent = msg;
    el.className = 'cform__result' + (kind ? ' is-' + kind : '');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!val('qBody')) { say('질문 내용을 입력해 주세요.', 'bad'); form.querySelector('#qBody').focus(); return; }
    if (!val('qContact')) { say('회신받으실 연락처나 이메일을 입력해 주세요.', 'bad'); form.querySelector('#qContact').focus(); return; }
    var agree = form.querySelector('#qAgree');
    if (agree && !agree.checked) {
      say('개인정보 수집·이용에 동의해 주셔야 접수됩니다.', 'bad');
      agree.focus();
      return;
    }

    var pub = form.querySelector('#qPublic');
    var body = [
      '[질의응답 접수]',
      '분야    : ' + (val('qCat') || '미선택'),
      '연락처  : ' + val('qContact'),
      '공개여부: ' + (pub && pub.checked ? '공개 가능 (개인정보 제외)' : '비공개 회신 희망'),
      '',
      '[질문]',
      val('qBody'),
      '',
      '— 개인정보 수집·이용에 동의함'
    ].join('\n');

    var url = 'mailto:' + TO +
      '?subject=' + encodeURIComponent('[질문] ' + (val('qCat') || '법률') + ' — ' + val('qBody').slice(0, 20)) +
      '&body=' + encodeURIComponent(body);
    if (url.length > 1800) {
      say('내용이 길어 메일 프로그램으로 넘기기 어렵습니다. 내용을 복사해 ' + TO + ' 으로 보내주세요.', 'bad');
      return;
    }
    window.location.href = url;
    say('메일 프로그램을 열었습니다. 보내기를 누르시면 접수됩니다.', 'ok');
  });
})();
