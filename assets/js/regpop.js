/* 제이엘 등기센터 안내 팝업 — 메인 첫 방문 시 1회

   "오늘 하루 보지 않기"는 localStorage 에 날짜만 저장한다.
   개인정보는 담지 않으며, 서버로 전송되지 않는다.
*/
(function () {
  'use strict';

  var KEY = 'jl.regpop.hideUntil';
  var today = new Date().toISOString().slice(0, 10);
  if (localStorage.getItem(KEY) === today) return;

  // 스크롤 없이 바로 뜨면 거슬리므로 잠깐 뒤에 띄운다
  var DELAY = 1400;

  document.addEventListener('DOMContentLoaded', function () {
    setTimeout(open, DELAY);
  });

  function open() {
    var pop = document.createElement('div');
    pop.className = 'regpop';
    pop.setAttribute('role', 'complementary');
    pop.setAttribute('aria-label', '제이엘 등기센터 안내');
    pop.innerHTML =
      '<div class="regpop__box">' +
        '<p class="regpop__en">JL REGISTRATION CENTER</p>' +
        '<h3>제이엘 등기센터</h3>' +
        '<p>등기 절차를 한 곳에서. 방문하지 않으셔도 됩니다.</p>' +
        '<ul class="regpop__list">' +
          '<li>단체등기 신청 · 개별등기 접수</li>' +
          '<li>등기 진행조회</li>' +
          '<li>등기비용 계산</li>' +
        '</ul>' +
        '<div class="regpop__acts">' +
          '<a class="btn btn--fill" href="dongtan.html">등기센터 바로가기 <span class="arrow">→</span></a>' +
        '</div>' +
        '<div class="regpop__foot">' +
          '<button type="button" data-hide-today>오늘 하루 보지 않기</button>' +
          '<button type="button" data-close>닫기</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(pop);

    function close() {
      pop.remove();
      document.removeEventListener('keydown', onKey);
    }
    function onKey(e) { if (e.key === 'Escape') close(); }

    pop.querySelectorAll('[data-close]').forEach(function (b) {
      b.addEventListener('click', close);
    });
    pop.querySelector('[data-hide-today]').addEventListener('click', function () {
      localStorage.setItem(KEY, today);
      close();
    });
    document.addEventListener('keydown', onKey);
  }
})();
