/* 상단 메뉴 하위 항목 — 마우스를 올리면 펼쳐진다.
   페이지마다 헤더를 고쳐 넣지 않고, 이 파일 하나로 20개 페이지에 같은 메뉴를 붙인다.
   - 상위 메뉴 링크는 그대로 살아 있다 (눌러서 그 페이지로 갈 수 있다)
   - 마우스 · 키보드(Tab) 어느 쪽으로도 열린다
   - 모바일에서는 펼침을 쓰지 않고 전체 목록을 세로로 보여준다
*/
(function () {
  'use strict';

  var header = document.querySelector('.header');
  var gnb = header && header.querySelector('.gnb');
  if (!gnb) return;

  /* 상위 메뉴 파일명 → 하위 항목.
     lead 는 펼침 왼쪽에 놓이는 소개글이다. */
  var MENU = {
    'about.html': {
      lead: { t: '법인소개', d: '소통 · 공감 · 신뢰' },
      items: [
        { t: '법인 개요', h: 'about.html#intro', d: '설립 배경과 운영 원칙' },
        { t: '구성원', h: 'lawyers.html', d: '구성원변호사 3인 · 소속변호사 3인' },
        { t: '지명원 내려받기', h: 'about.html#profile', d: 'PDF 회사소개서' },
        { t: '오시는 길', h: 'contact.html', d: '서초 본사무소 · 동탄 분사무소' }
      ]
    },
    'practice.html': {
      lead: { t: '업무분야', d: '여섯 개 분야를 나눠 맡되, 한 사건은 함께 봅니다.' },
      items: [
        { t: '재건축 · 재개발', h: 'redevelopment.html', d: '조합 · 관리처분 · 현금청산' },
        { t: '하자소송', h: 'practice.html#defect', d: '보수 청구 · 보증금 소송' },
        { t: '단체등기', h: 'registry.html', d: '입주 아파트 · 분양전환 · 대지권' },
        { t: '민사 · 형사', h: 'practice.html#civil', d: '부동산 · 채권회수 · 수사대응' },
        { t: '기업법무', h: 'corporate.html', d: '법률자문 · 법인등기' },
        { t: '상속 · 이혼 · 조세', h: 'practice.html#tax', d: '조세심판 · 상속 · 유류분' }
      ]
    },
    /* 단체등기 아래에 등기센터가 있고, 계산기 · 개별등기 접수는
       등기센터 안의 기능이다. 같은 줄에 늘어놓지 않는다.
       단지 단위 단체등기 접수는 아파트친구에서 받는다. */
    /* 단체등기 업무를 실제로 굴리는 플랫폼이 아파트친구다.
       메뉴를 둘로 나누면 같은 사업이 두 개로 보인다. 하나로 묶는다. */
    'registry.html': {
      lead: { t: '단체등기', d: '입주 단지 단위 소유권이전등기' },
      items: [
        { t: '업무 안내', h: 'registry.html', d: '범위 · 절차 · 기간' },
        { t: '등기센터', h: 'dongtan.html', d: '신청 · 비용 · 진행상황', children: [
          { t: '개별등기 신청', h: 'dongtan.html#apply', d: '소유권이전 · 근저당 · 상속' },
          { t: '등기비용 계산기', h: 'dongtan.html#calc', d: '취득세 · 채권 · 보수액' },
          { t: '진행 현황', h: 'dongtan.html#track', d: '접수 ~ 등기필증 수령' },
          { t: '구비서류', h: 'dongtan.html#docs', d: '매매 · 상속 · 증여별' }
        ] },
        { t: '아파트친구', h: 'https://kingjin77-rgb.github.io/apt-friend/',
          d: '입주예정자협의회 지원센터', ext: true, children: [
          { t: '단체등기 접수', h: 'https://kingjin77-rgb.github.io/apt-friend/group-registration.html', d: '단지 단위', ext: true },
          { t: '전 과정 26단계', h: 'https://kingjin77-rgb.github.io/apt-friend/roadmap.html', d: '발족 ~ 해체', ext: true },
          { t: '위임장 접수', h: 'https://kingjin77-rgb.github.io/apt-friend/poa-system.html', d: '세대별 전자접수', ext: true },
          { t: '서식 생성', h: 'https://kingjin77-rgb.github.io/apt-friend/document-center.html', d: '회칙 · 공고문', ext: true }
        ] }
      ]
    },
    'law.html': {
      lead: { t: '법률정보', d: '법제처 자료를 매일 06시에 자동으로 받아옵니다.' },
      items: [
        { t: '자주 묻는 법률상식', h: 'qna.html', d: '실무에서 반복되는 질문과 답', hot: true },
        { t: '공동주택 관련 법령', h: 'law.html#acts', d: '주택법 등 12종' },
        { t: '최신 판례', h: 'law.html#cases', d: '하자 · 재건축 · 등기 쟁점' }
      ]
    },
    'magazine.html': {
      lead: { t: '제이엘 매거진', d: 'THE ASSET 발행호와 단지별 검토보고서.' },
      items: [
        { t: 'THE ASSET', h: 'magazine.html#asset', d: '발행호 전체' },
        { t: '검토보고서', h: 'magazine.html#reports', d: '분양공고 분석 보고서' },
        { t: '하자소송 Q&A 연재', h: 'lawyers/ha-hye-yong.html', d: '하혜용 변호사' }
      ]
    },
    'contact.html': {
      lead: { t: '오시는 길', d: '서초 본사무소와 동탄 분사무소를 운영합니다.' },
      items: [
        { t: '본사무소 · 서초', h: 'contact.html#seocho', d: '02-537-0123' },
        { t: '분사무소 · 동탄', h: 'contact.html#dongtan', d: '단체등기 1899-4252' },
        { t: '상담 문의', h: 'contact.html#consult', d: '내용을 적어 바로 보내기' }
      ]
    }
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // 하위 페이지(lawyers/*.html)에서는 경로 앞에 ../ 가 붙어야 한다
  var up = /\/lawyers\//.test(location.pathname) ? '../' : '';
  function href(h) { return up + h; }

  // 외부 주소는 그대로 쓰고, 사이트 안 주소만 경로를 맞춘다
  function link(it) {
    var url = it.ext ? it.h : href(it.h);
    return '<a href="' + esc(url) + '"' +
           (it.ext ? ' target="_blank" rel="noopener" class="is-ext"' : '') + '>' +
             '<b>' + esc(it.t) + '</b><small>' + esc(it.d) + '</small>' +
           '</a>';
  }
  // 하위 기능이 있으면 한 단 들여 묶는다 — 같은 줄에 늘어놓으면 관계가 사라진다
  function row(it) {
    if (!it.children) return '<li>' + link(it) + '</li>';
    return '<li class="has-sub">' + link(it) +
             '<ul class="gnbdrop__sub">' +
               it.children.map(function (c) { return '<li>' + link(c) + '</li>'; }).join('') +
             '</ul>' +
           '</li>';
  }

  var links = Array.prototype.slice.call(gnb.querySelectorAll('a'));
  var open = null;
  var closeTimer = null;

  links.forEach(function (a) {
    // 외부 링크(아파트친구)는 펼치지 않는다
    if (a.classList.contains('gnb__ext')) return;
    var key = (a.getAttribute('href') || '').split('/').pop().split('#')[0];
    var conf = MENU[key];
    if (!conf) return;

    var wrap = document.createElement('div');
    wrap.className = 'gnb__item';
    a.parentNode.insertBefore(wrap, a);
    wrap.appendChild(a);
    a.setAttribute('aria-expanded', 'false');

    var panel = document.createElement('div');
    panel.className = 'gnbdrop';
    panel.hidden = true;
    panel.innerHTML =
      '<div class="gnbdrop__in">' +
        '<div class="gnbdrop__lead">' +
          '<p class="gnbdrop__t">' + esc(conf.lead.t) + '</p>' +
          '<p class="gnbdrop__d">' + esc(conf.lead.d) + '</p>' +
          '<a class="gnbdrop__all" href="' + esc(href(key)) + '">전체 보기 <span>→</span></a>' +
        '</div>' +
        '<ul class="gnbdrop__list">' + conf.items.map(row).join('') + '</ul>' +
      '</div>';
    wrap.appendChild(panel);

    function show() {
      clearTimeout(closeTimer);
      if (open && open !== wrap) hide(open);
      panel.hidden = false;
      // 판의 윗변을 메뉴 글자 밑줄에 맞춘다. 헤더 위쪽부터 잰 거리다
      var hb = header.getBoundingClientRect();
      var top = Math.round(wrap.getBoundingClientRect().bottom - hb.top);
      wrap.style.setProperty('--gnb-top', top + 'px');
      // 하위 항목의 왼쪽 끝을 상단 메뉴 첫 글자에 맞춘다
      wrap.style.setProperty('--gnb-left',
        Math.round(gnb.getBoundingClientRect().left - hb.left) + 'px');
      // 항목 수가 메뉴마다 달라 헤더가 늘어날 높이도 그때그때 재야 한다.
      // 판이 헤더 아래로 삐져나가는 만큼만 늘린다
      var over = top + panel.offsetHeight + 12 - Math.round(hb.height);
      header.style.setProperty('--drop-h', Math.max(0, over) + 'px');
      // hidden 해제 직후 전환이 먹도록 한 프레임 뒤에 상태를 준다
      requestAnimationFrame(function () { wrap.classList.add('is-open'); });
      a.setAttribute('aria-expanded', 'true');
      header.classList.add('has-drop');
      open = wrap;
    }
    function hide(w) {
      w = w || wrap;
      var p = w.querySelector('.gnbdrop');
      var link = w.querySelector('a');
      w.classList.remove('is-open');
      link.setAttribute('aria-expanded', 'false');
      if (open === w) { open = null; header.classList.remove('has-drop'); }
      setTimeout(function () { if (!w.classList.contains('is-open')) p.hidden = true; }, 260);
    }

    wrap.addEventListener('mouseenter', show);
    wrap.addEventListener('mouseleave', function () {
      // 마우스가 잠깐 벗어났다 돌아오는 경우가 잦아 여유를 둔다
      closeTimer = setTimeout(function () { hide(); }, 260);
    });
    wrap.addEventListener('focusin', show);
    wrap.addEventListener('focusout', function (e) {
      if (!wrap.contains(e.relatedTarget)) hide();
    });
    wrap.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { hide(); a.focus(); }
    });
  });

  gnb.classList.add('gnb--drop');
})();
