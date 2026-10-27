/* 손님 화면 공용 스크립트 — CSP(script-src 'self') 때문에 인라인 핸들러 대신 여기서 묶는다. */
(function () {
  'use strict';
  // <select data-toggle-etc="대상id" data-etc-value="직접입력"> — 고른 값이 같을 때만 대상 칸을 보인다.
  document.querySelectorAll('select[data-toggle-etc]').forEach(function (sel) {
    var target = document.getElementById(sel.getAttribute('data-toggle-etc'));
    if (!target) { return; }
    var want = sel.getAttribute('data-etc-value');
    function sync() { target.hidden = sel.value !== want; }
    sel.addEventListener('change', sync);
    sync();
  });
})();
