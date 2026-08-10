/* 배포한 내용이 바로 보이게 한다.

   GitHub Pages 는 HTML 에 10분짜리 캐시를 붙인다. 그래서 고친 뒤에도 브라우저가
   한동안 이전 화면을 그대로 보여준다. 링크를 다시 보내 줄 수 없는 상황(회의 중 등)에서는
   이걸 사람이 눈치채기 어렵다.

   그래서 화면이 스스로 확인한다. version.json 에 적힌 번호와 지금 화면이 쓰고 있는
   번호가 다르면, 주소에 번호를 붙여 한 번만 다시 불러온다. 번호가 붙으면 캐시가 걸리지
   않으므로 확실히 최신본이 뜬다.

   같은 번호로는 다시 불러오지 않으므로 새로고침이 반복되지 않는다. */
(function () {
  'use strict';
  if (!window.fetch || !window.sessionStorage) return;

  // 지금 화면이 쓰고 있는 번호 — 스타일시트에 붙은 ?v= 값을 그대로 읽는다
  var css = document.querySelector('link[rel=stylesheet][href*="style.css"]');
  var here = css && (css.getAttribute('href').split('?v=')[1] || '');
  if (!here) return;

  function go(build) {
    var key = 'jl.fresh.' + build;
    // 이미 이 번호로 한 번 시도했으면 더 하지 않는다
    if (sessionStorage.getItem(key)) return;
    sessionStorage.setItem(key, '1');
    var url = location.pathname + '?b=' + encodeURIComponent(build) + location.hash;
    location.replace(url);
  }

  // 변호사 개별 페이지는 한 단 아래에 있어 경로 앞을 맞춰 준다
  var up = /\/lawyers\//.test(location.pathname) ? '../' : '';

  fetch(up + 'version.json?_=' + Date.now(), { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d || !d.build) return;
      if (String(d.build) !== String(here)) go(d.build);
    })
    .catch(function () { /* 확인 못 하면 그냥 둔다 */ });
})();
