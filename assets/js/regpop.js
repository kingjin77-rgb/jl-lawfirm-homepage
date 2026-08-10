/* 오른쪽 위 안내 카드 — 메인 첫 방문 시 1회

   등기센터와 아파트친구 두 장을 위아래로 세워 둔다.
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

  var CARDS = [
    {
      en: 'JL REGISTRATION CENTER',
      title: '등기센터',
      desc: '신청부터 등기필증 수령까지 방문 없이 진행합니다.',
      list: ['개별등기 신청', '진행 현황 조회', '등기비용 계산'],
      href: 'dongtan.html',
      cta: '등기센터 바로가기'
    },
    {
      en: 'APT FRIEND',
      title: '아파트친구',
      desc: '입주예정자협의회를 위한 별도 사이트입니다.',
      list: ['단지 단위 단체등기 신청', '협의회 전 과정 26단계', '위임장 접수 · 서식 생성'],
      href: 'https://kingjin77-rgb.github.io/apt-friend/',
      cta: '아파트친구 열기',
      ext: true
    }
  ];

  document.addEventListener('DOMContentLoaded', function () {
    setTimeout(open, DELAY);
  });

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function card(c, i) {
    return '' +
      '<div class="regpop__box" style="--d:' + (i * 120) + 'ms">' +
        '<p class="regpop__en">' + esc(c.en) + '</p>' +
        '<h3>' + esc(c.title) + '</h3>' +
        '<p>' + esc(c.desc) + '</p>' +
        '<ul class="regpop__list">' +
          c.list.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') +
        '</ul>' +
        '<div class="regpop__acts">' +
          '<a class="btn btn--fill" href="' + esc(c.href) + '"' +
            (c.ext ? ' target="_blank" rel="noopener"' : '') + '>' +
            esc(c.cta) + ' <span class="arrow">→</span></a>' +
        '</div>' +
      '</div>';
  }

  function open() {
    var pop = document.createElement('div');
    pop.className = 'regpop';
    pop.setAttribute('role', 'complementary');
    pop.setAttribute('aria-label', '제이엘 등기센터 · 아파트친구 안내');
    pop.innerHTML =
      CARDS.map(card).join('') +
      '<div class="regpop__foot">' +
        '<button type="button" data-hide-today>오늘 하루 보지 않기</button>' +
        '<button type="button" data-close>닫기</button>' +
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
