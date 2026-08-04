/* 법률정보 게시판 — data/law-feed.json 렌더링
   법제처 국가법령정보 Open API로 생성된 피드를 목록/검색/필터/페이지네이션으로 표시한다.
*/
(function () {
  'use strict';

  var root = document.querySelector('[data-board]');
  if (!root) return;

  var listEl = root.querySelector('[data-board-list]');
  var pageEl = root.querySelector('[data-board-page]');
  var totalEl = root.querySelector('[data-board-total]');
  var stampEl = root.querySelector('[data-board-stamp]');
  var filterEl = root.querySelector('[data-board-filter]');
  var formEl = root.querySelector('[data-board-search]');
  var inputEl = formEl ? formEl.querySelector('input') : null;

  var PER_PAGE = 10;
  var all = [];
  var view = [];
  var page = 1;
  var cat = 'all';
  var keyword = '';

  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  function apply() {
    var kw = keyword.trim().toLowerCase();
    view = all.filter(function (it) {
      if (cat !== 'all' && it.category !== cat) return false;
      if (!kw) return true;
      return (it.title + ' ' + it.summary + ' ' + it.category).toLowerCase().indexOf(kw) > -1;
    });
    page = 1;
    render();
  }

  function render() {
    if (totalEl) totalEl.innerHTML = '전체 <b>' + view.length + '</b>건';

    if (!view.length) {
      listEl.innerHTML = '<div class="board-empty">검색 결과가 없습니다.</div>';
      pageEl.innerHTML = '';
      return;
    }

    var start = (page - 1) * PER_PAGE;
    var rows = view.slice(start, start + PER_PAGE);

    listEl.innerHTML = rows.map(function (it) {
      var kind = it.type === 'prec' ? '판례' : '법령';
      return '' +
        '<article class="board-item ' + (it.type === 'prec' ? 'is-prec' : 'is-law') + '">' +
          '<a href="' + esc(it.link) + '" target="_blank" rel="noopener">' +
            '<span class="board-no">' + esc(it.no) + '</span>' +
            '<span class="board-cate">' + esc(kind) + ' · ' + esc(it.category) + '</span>' +
            '<span class="board-tit">' +
              '<strong>' + esc(it.title) + '</strong>' +
              '<p>' + esc(it.summary) + '</p>' +
            '</span>' +
            '<span class="board-date">' + esc(it.date) + '</span>' +
            '<span class="board-go">→</span>' +
          '</a>' +
        '</article>';
    }).join('');

    // 페이지네이션
    var last = Math.ceil(view.length / PER_PAGE);
    var html = '<button type="button" data-go="prev"' + (page === 1 ? ' disabled' : '') + '>이전</button>';
    for (var i = 1; i <= last; i++) {
      html += '<button type="button" data-go="' + i + '"' +
              (i === page ? ' class="is-on"' : '') + '>' + i + '</button>';
    }
    html += '<button type="button" data-go="next"' + (page === last ? ' disabled' : '') + '>다음</button>';
    pageEl.innerHTML = html;
  }

  if (pageEl) {
    pageEl.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-go]');
      if (!b || b.disabled) return;
      var g = b.dataset.go;
      var last = Math.ceil(view.length / PER_PAGE);
      if (g === 'prev') page = Math.max(1, page - 1);
      else if (g === 'next') page = Math.min(last, page + 1);
      else page = parseInt(g, 10);
      render();
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (filterEl) {
    filterEl.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-filter]');
      if (!b) return;
      cat = b.dataset.filter;
      filterEl.querySelectorAll('button').forEach(function (x) {
        x.classList.toggle('is-on', x === b);
      });
      apply();
    });
  }

  if (formEl) {
    formEl.addEventListener('submit', function (e) {
      e.preventDefault();
      keyword = inputEl ? inputEl.value : '';
      apply();
    });
  }

  fetch('data/law-feed.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      all = d.items || [];
      view = all.slice();
      if (stampEl && d.generatedAt) {
        stampEl.textContent = d.generatedAt + ' 기준 · 출처 ' + (d.source || 'law.go.kr');
      }
      render();
    })
    .catch(function (err) {
      listEl.innerHTML =
        '<div class="board-empty">법률정보를 불러오지 못했습니다.<br>' +
        '<span style="font-size:13px">(' + esc(err.message) + ')</span></div>';
      console.error('[board]', err);
    });
})();
