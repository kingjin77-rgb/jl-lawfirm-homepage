/* 매거진 렌더링 — data/magazine.json
   발행호(최신호 배너 + 수록 기사)와 검토보고서 카드를 데이터에서 그린다.
   관리자 페이지(admin/)가 이 JSON을 편집하므로, 콘텐츠 추가에 HTML 수정이 필요 없다.
*/
(function () {
  'use strict';

  var featureEl = document.querySelector('[data-mag-feature]');
  var contentsEl = document.querySelector('[data-mag-contents]');
  var listEl = document.querySelector('[data-maglist]');
  if (!featureEl && !listEl) return;

  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };
  // 제목에는 <br> 만 허용한다
  var titleHtml = function (s) { return esc(s).replace(/&lt;br\s*\/?&gt;/gi, '<br>'); };

  function renderFeature(issue) {
    if (!featureEl || !issue) return;
    featureEl.innerHTML =
      '<div class="magfeature__thumb">' +
        (issue.cover ? '<img src="' + esc(issue.cover) + '" alt="' + esc(issue.title.replace(/<br\s*\/?>/gi, ' ')) + ' 표지">' : '') +
      '</div>' +
      '<div class="magfeature__body">' +
        '<p class="magfeature__cat">' + esc(issue.label) + '</p>' +
        '<h3 class="magfeature__title">' + titleHtml(issue.title) + '</h3>' +
        '<p class="magfeature__desc">' + esc(issue.desc) + '</p>' +
        (issue.tags && issue.tags.length
          ? '<ul class="magcard__tags" style="margin-top:24px">' +
            issue.tags.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>'
          : '') +
        (issue.link
          ? '<p style="margin-top:30px"><a class="btn btn--ghost" href="' + esc(issue.link) +
            '" target="_blank" rel="noopener">' + esc(issue.no) + '호 전체 보기 &#8594;</a></p>'
          : '') +
      '</div>';
  }

  function renderContents(issue) {
    if (!contentsEl || !issue || !issue.contents) return;
    contentsEl.innerHTML = issue.contents.map(function (c, i) {
      var no = String(i + 1).padStart(2, '0');
      return '' +
        '<article class="board-item' + (c.kind === 'prec' ? ' is-prec' : '') + '">' +
          '<a href="' + esc(issue.link) + '" target="_blank" rel="noopener">' +
            '<span class="board-no">' + no + '</span>' +
            '<span class="board-cate">' + esc(c.cate) + '</span>' +
            '<span class="board-tit"><strong>' + esc(c.title) + '</strong>' +
              '<p>' + esc(c.desc) + '</p></span>' +
            '<span class="board-date">' + esc(c.page) + '</span>' +
            '<span class="board-go">&#8594;</span>' +
          '</a>' +
        '</article>';
    }).join('');
  }

  function renderReports(reports) {
    if (!listEl || !reports) return;
    listEl.innerHTML = reports.map(function (r, i) {
      var more = r.link
        ? '<a href="' + esc(r.link) + '" target="_blank" rel="noopener" class="more">보고서 보기 &#8594;</a>'
        : '<span class="more">준비 중</span>';
      return '' +
        '<article class="magcard reveal" data-cat="' + esc(r.cat) + '"' +
                (i % 3 ? ' data-delay="' + (i % 3) * 80 + '"' : '') + '>' +
          '<div class="magcard__thumb" style="background:' + esc(r.gradient || '#1b2b57') + '">' +
            (r.thumb ? '<img src="' + esc(r.thumb) + '" alt="" loading="lazy">' : '') +
            '<span class="magcard__cat">' + esc(catLabel(r.cat)) + '</span>' +
          '</div>' +
          '<div class="magcard__body">' +
            '<h3 class="magcard__title">' + esc(r.title) + '</h3>' +
            '<p class="magcard__desc">' + esc(r.desc) + '</p>' +
            (r.tags && r.tags.length
              ? '<ul class="magcard__tags">' + r.tags.map(function (t) {
                  return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>'
              : '') +
            '<p class="magcard__meta"><span>법무법인 제이엘</span>' + more + '</p>' +
          '</div>' +
        '</article>';
    }).join('');

    // 리빌 재적용
    if (window.JLReveal) window.JLReveal(listEl.querySelectorAll('.reveal'));
  }

  function catLabel(cat) {
    return { '공고검토': '분양공고 검토', '민간임대': '민간임대', '하자': '하자 · 개선요구' }[cat] || cat;
  }

  fetch('data/magazine.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      var latest = (d.issues || [])[0];
      renderFeature(latest);
      renderContents(latest);
      renderReports(d.reports);
    })
    .catch(function (err) {
      console.error('[magazine]', err);
      if (listEl) {
        listEl.innerHTML = '<div class="board-empty">매거진 정보를 불러오지 못했습니다. (' + esc(err.message) + ')</div>';
      }
    });
})();
