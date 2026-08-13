/* 오른쪽 위 안내 카드 — 상단 메뉴에서 단체등기를 눌렀을 때 연다.

   등기센터와 아파트친구 두 장을 위아래로 세워 둔다.
   단체등기는 들어가는 문이 둘(개별등기는 등기센터, 단지 단위는 아파트친구)이라
   메뉴를 누른 사람에게 어느 쪽인지 먼저 고르게 하는 편이 헤매지 않는다.

   묻지도 않았는데 첫 화면에서 저절로 뜨던 것은 그만둔다. 누른 사람에게만 보인다.
   "오늘 하루 보지 않기"는 localStorage 에 날짜만 저장한다.
   개인정보는 담지 않으며, 서버로 전송되지 않는다.
*/
(function () {
  'use strict';

  var KEY = 'jl.regpop.hideUntil';

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

  /* 상단 메뉴의 단체등기를 누르면 페이지로 넘어가는 대신 이 카드를 연다.
     메뉴는 gnb.js 가 감싸므로, 눌린 지점에서 거슬러 올라가 찾는다.
     "오늘 하루 보지 않기"를 눌러 둔 사람은 카드를 건너뛰고 바로 페이지로 보낸다. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('.gnb a') : null;
    if (!a) return;
    var href = (a.getAttribute('href') || '').split('/').pop().split('#')[0];
    if (href !== 'registry.html') return;
    if (localStorage.getItem(KEY) === new Date().toISOString().slice(0, 10)) return;
    if (document.querySelector('.regpop')) return;
    e.preventDefault();
    open();
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
      // 절차부터 보고 싶은 사람도 있다. 원래 가려던 곳으로 가는 길을 남겨 둔다
      '<p class="regpop__plain"><a href="registry.html">단체등기 업무 안내부터 보기 →</a></p>' +
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
