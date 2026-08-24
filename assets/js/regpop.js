/* 오른쪽 위 안내 카드 — 단체등기 페이지(registry.html)에 도착하면 뜬다.

   등기센터와 아파트친구 두 장을 위아래로 세워 둔다.
   단체등기는 들어가는 문이 둘(개별등기는 등기센터, 아파트 단위는 아파트친구)이라
   이 페이지에 온 사람에게 어느 쪽인지 먼저 보여 주는 편이 헤매지 않는다.

   메뉴 클릭은 그냥 페이지로 넘어간다 — 가로채지 않는다.
   페이지에 도착한 뒤에만 이 카드가 뜬다.
   "오늘 하루 보지 않기"는 localStorage 에 날짜만 저장한다.
   개인정보는 담지 않으며, 서버로 전송되지 않는다.
*/
(function () {
  'use strict';

  // 단체등기 페이지에서만 뜬다. 다른 페이지에 이 스크립트가 실려 있어도 조용히 아무 일도 안 한다.
  var page = location.pathname.split('/').pop() || 'index.html';
  if (page !== 'registry.html') return;

  var KEY = 'jl.regpop.hideUntil';
  if (localStorage.getItem(KEY) === new Date().toISOString().slice(0, 10)) return;

  // 도착하자마자 뜨면 화면을 채 보기도 전에 가려 거슬린다. 잠깐 두고 띄운다.
  var DELAY = 900;

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
      list: ['아파트 단체등기 신청', '협의회 전 과정 26단계', '위임장 접수 · 서식 생성'],
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
    if (document.querySelector('.regpop')) return;
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
      localStorage.setItem(KEY, new Date().toISOString().slice(0, 10));
      close();
    });
    document.addEventListener('keydown', onKey);
  }
})();
