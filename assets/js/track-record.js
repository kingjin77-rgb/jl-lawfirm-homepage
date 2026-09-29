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

  /* 단지 수 세어 올리기 — 목록은 fetch 뒤에 그려져 motion.js 카운터가 못 잡는다.
     화면에 들어올 때 0부터 실제 개수까지 오르고, 끝값은 항상 실제 개수 그대로다. */
  function countUp(el, target) {
    el.textContent = target;                      // 관찰이 안 되는 환경에서도 값은 보인다
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || !('IntersectionObserver' in window) || !(target > 0)) return;
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        io.unobserve(el);
        var dur = 1000, t0 = null;
        requestAnimationFrame(function step(t) {
          if (t0 === null) t0 = t;
          var p = Math.min((t - t0) / dur, 1);
          el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
          if (p < 1) requestAnimationFrame(step);
          else el.textContent = target;
        });
      });
    }, { threshold: 0.5 });
    io.observe(el);
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
      if (cntEl) countUp(cntEl, items.length);
      root.removeAttribute('hidden');
    })
    .catch(function (err) {
      console.error('[track-record]', err);
      root.remove();   // 못 불러오면 빈 목록을 보이느니 감춘다
    });
})();
