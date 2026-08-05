/* 업무분야 페이지 — 분야별 최신 판례 자동 표시
   data/law-feed.json 은 법제처 API에서 매일 갱신되므로
   이 목록도 손대지 않아도 최신으로 유지된다.

   사용: <div data-prec-feed="하자소송" data-prec-limit="5"></div>
*/
(function () {
  'use strict';

  var slots = document.querySelectorAll('[data-prec-feed]');
  if (!slots.length) return;

  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  fetch('data/law-feed.json')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var items = (data.items || []).filter(function (i) { return i.type === 'prec'; });

      slots.forEach(function (slot) {
        var cat = slot.getAttribute('data-prec-feed');
        var limit = parseInt(slot.getAttribute('data-prec-limit') || '5', 10);
        var list = items
          .filter(function (i) { return i.category === cat; })
          .slice(0, limit);

        if (!list.length) { slot.hidden = true; return; }

        slot.innerHTML =
          '<p class="pfeed__head">관련 최신 판례 <span>법제처 국가법령정보 · 매일 자동 갱신</span></p>' +
          '<ul class="pfeed__list">' +
          list.map(function (i) {
            return '<li><a href="' + esc(i.link) + '" target="_blank" rel="noopener">' +
              '<span class="pfeed__title">' + esc(i.title) + '</span>' +
              '<span class="pfeed__meta">' + esc(i.summary || '') +
                (i.date ? ' · ' + esc(i.date) : '') + '</span>' +
            '</a></li>';
          }).join('') +
          '</ul>' +
          '<p class="pfeed__more"><a href="law.html">법률정보 전체 보기 →</a></p>';
      });
    })
    .catch(function () { /* 파일이 없으면 아무것도 표시하지 않는다 */ });
})();
