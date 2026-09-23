/* 메인 최신 법률정보 — data/law-feed.json 에서 가장 최근 세 건만 보여준다.
   법령은 시행일, 판례는 선고일 기준으로 최근 순서다.
   불러오지 못하면 빈 칸을 보이지 않도록 구역 전체를 감춘다. */
(function () {
  'use strict';

  var root = document.querySelector('[data-latest-law]');
  if (!root) return;
  var listEl = root.querySelector('[data-ll-list]');

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  var KIND = { law: { t: '법령', d: '시행' }, prec: { t: '판례', d: '선고' } };

  fetch('data/law-feed.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      var items = (d.items || []).filter(function (it) {
        return it && it.title && it.link && KIND[it.type];
      });
      if (!items.length) throw new Error('법률정보가 비어 있습니다');

      // 날짜(YYYY.MM.DD)는 글자 순서가 곧 날짜 순서다. 같은 날이면 나중에 받은 항목이 앞이다
      items.sort(function (a, b) {
        var k = String(b.date || '').localeCompare(String(a.date || ''));
        return k || ((b.no || 0) - (a.no || 0));
      });

      listEl.innerHTML = items.slice(0, 3).map(function (it) {
        var k = KIND[it.type];
        return '' +
          '<li>' +
            '<a class="lawnew__card" href="' + esc(it.link) + '" target="_blank" rel="noopener">' +
              '<span class="lawnew__tags">' +
                '<span class="lawnew__kind lawnew__kind--' + esc(it.type) + '">' + k.t + '</span>' +
                (it.category ? '<span class="lawnew__cat">' + esc(it.category) + '</span>' : '') +
              '</span>' +
              '<b class="lawnew__title">' + esc(it.title) + '</b>' +
              (it.summary ? '<span class="lawnew__sum">' + esc(it.summary) + '</span>' : '') +
              // 법령 요약에는 이미 "시행 날짜"가 들어 있어 같은 줄을 두 번 쓰지 않는다
              (it.date && String(it.summary || '').indexOf(k.d + ' ' + it.date) < 0
                ? '<span class="lawnew__date">' + k.d + ' ' + esc(it.date) + '</span>' : '') +
            '</a>' +
          '</li>';
      }).join('');
      root.removeAttribute('hidden');
    })
    .catch(function (err) {
      console.error('[latest-law]', err);
      root.remove();
    });
})();
