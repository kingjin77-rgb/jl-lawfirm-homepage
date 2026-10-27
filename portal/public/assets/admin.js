/* 관리자 화면 공용 스크립트 — CSP 때문에 onclick/onsubmit 인라인 대신 data-confirm 으로 묶는다. */
(function () {
  'use strict';
  // <form data-confirm="문구"> — 제출 전 확인창
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var msg = f.getAttribute && f.getAttribute('data-confirm');
    // 제출 버튼 쪽 문구가 우선 (같은 폼에 버튼이 여럿인 경우)
    var btn = e.submitter;
    if (btn && btn.getAttribute('data-confirm')) { msg = btn.getAttribute('data-confirm'); }
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  }, true);
  // <a data-confirm="문구"> — 이동 전 확인창
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-confirm]') : null;
    if (a && !window.confirm(a.getAttribute('data-confirm'))) { e.preventDefault(); }
  }, true);
})();
