/* 분야별 수행사례 — data/cases.json을 읽어 [data-cases="분야"] 자리에 카드로 그린다.
   해당 분야 사례가 하나도 없으면 블록 전체를 화면에서 지운다.
   사례 추가는 cases.json에 항목을 넣는 것만으로 끝난다. */
(function () {
  'use strict';
  var slots = document.querySelectorAll('[data-cases]');
  if (!slots.length) return;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  fetch('data/cases.json?v=' + (window.JL_ASSET_V || '1'))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var all = (data && data.cases) || [];
      slots.forEach(function (slot) {
        var cat = slot.getAttribute('data-cases');
        var list = all.filter(function (c) { return c.category === cat; });
        if (!list.length) { slot.remove(); return; }
        list.sort(function (a, b) { return (b.year || 0) - (a.year || 0); });

        var cards = list.map(function (c) {
          return (
            '<article class="casecard">' +
              '<p class="casecard__meta"><span>' + esc(c.year) + '</span>' + esc(cat) + '</p>' +
              '<h4 class="casecard__title">' + esc(c.title) + '</h4>' +
              (c.result ? '<p class="casecard__result">' + esc(c.result) + '</p>' : '') +
              (c.point ? '<p class="casecard__point">' + esc(c.point) + '</p>' : '') +
            '</article>'
          );
        }).join('');

        slot.innerHTML =
          '<h3 class="cases__head">주요 수행사례</h3>' +
          '<div class="cases__grid">' + cards + '</div>';
        slot.hidden = false;
      });
    })
    .catch(function () {
      slots.forEach(function (slot) { slot.remove(); });
    });
})();
