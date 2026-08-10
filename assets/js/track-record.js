/* 수행 단지 — data/registry.json 의 complexes 를 그대로 보여준다.

   같은 단지가 "조합 OO" 로 한 번 더 들어 있는 경우가 있어 묶어서 센다.
   숫자를 부풀리면 나중에 확인 요청이 들어왔을 때 답할 수 없다.
*/
(function () {
  'use strict';

  var root = document.querySelector('[data-track-record]');
  if (!root) return;

  var listEl = root.querySelector('[data-tr-list]');
  var cntEl = root.querySelector('[data-tr-count]');

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  fetch('data/registry.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      var raw = d.complexes || [];

      // "조합 OO아파트" 는 같은 단지의 조합 건이므로 단지 이름으로 묶는다
      var seen = Object.create(null);
      var items = [];
      raw.forEach(function (name) {
        var base = String(name).replace(/^조합\s+/, '').trim();
        if (!base) return;
        if (seen[base]) { seen[base].union = true; return; }
        seen[base] = { name: base, union: /^조합\s+/.test(name) };
        items.push(seen[base]);
      });
      items.sort(function (a, b) { return a.name.localeCompare(b.name, 'ko'); });

      listEl.innerHTML = items.map(function (it) {
        return '<li><span>' + esc(it.name) + '</span>' +
               (it.union ? '<em>조합 포함</em>' : '') + '</li>';
      }).join('');
      if (cntEl) cntEl.textContent = items.length;
      root.removeAttribute('hidden');
    })
    .catch(function (err) {
      console.error('[track-record]', err);
      root.remove();   // 못 불러오면 빈 목록을 보이느니 감춘다
    });
})();
