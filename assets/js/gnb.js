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
        { t: '오시는 길', h: 'contact.html', d: '서초 본사무소 · 동탄 분사무소' }
      ]
    },
    'practice.html': {
      lead: { t: '업무분야', d: '여섯 개 분야를 나눠 맡되, 한 사건은 함께 봅니다.' },
      /* 분야 순서는 이 여섯 개로 사이트 전체가 같아야 한다.
         상단 메뉴 · 메인 주요업무 · 업무분야 페이지 카드와 본문이 모두 이 차례를 따른다. */
      items: [
        { t: '기업법무', h: 'corporate.html', d: '법률자문 · 법인등기' },
        { t: '민사 · 형사', h: 'practice.html#civil', d: '부동산 · 채권회수 · 수사대응' },
        { t: '하자소송', h: 'practice.html#defect', d: '보수 청구 · 보증금 소송' },
        { t: '재건축 · 재개발', h: 'redevelopment.html', d: '조합 · 관리처분 · 현금청산' },
        { t: '가사 · 상속 · 이혼', h: 'practice.html#family', d: '이혼 · 상속재산분할 · 유류분' },
        { t: '단체등기', h: 'registry.html', d: '입주 아파트 · 분양전환 · 대지권' }
      ]
    },
    /* 단체등기 아래에 등기센터가 있고, 계산기 · 개별등기 접수는
       등기센터 안의 기능이다. 같은 줄에 늘어놓지 않는다.
       개별등기와 아파트 단위 단체등기 모두 등기센터 한 곳에서 받는다. */
    'registry.html': {
      lead: { t: '단체등기', d: '입주 아파트 소유권이전등기' },
      items: [
        { t: '등기센터', h: 'dongtan.html', d: '신청 · 비용 · 진행상황' }
      ]
    },
    'law.html': {
      lead: { t: '법률정보', d: '법제처 자료를 매일 06시에 자동으로 받아옵니다.' },
      items: [
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
    /* 오시는 길은 페이지 자체가 사무소 두 곳과 상담 폼을 전부 보여준다.
       펼침 안에 같은 항목을 또 늘어놓을 필요가 없어 뺐다. */
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

  links.forEach(function (a) {
    // 외부 링크는 펼치지 않는다
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
      // 이미 열려 있는 메뉴로 돌아온 경우 — 다시 재면 이미 늘어난 헤더 높이를 기준으로 계산돼
      // 늘어난 만큼이 도로 줄어든다(펼침 글자가 어두운 배경 위로 빠진다). 그대로 둔다.
      if (open === wrap && wrap.classList.contains('is-open')) return;
      if (open && open !== wrap && open._cancelClose) open._cancelClose();
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
      // 늘어나기 전 헤더 높이(--header-h)를 기준으로 잰다. 다른 메뉴에서 넘어오면 이미 늘어나 있다
      var baseH = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || Math.round(hb.height);
      var over = top + panel.offsetHeight + 20 - baseH;
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

    /* 하위 항목은 메뉴 첫 글자 아래(왼쪽)에서 시작한다.
       오른쪽 메뉴에서 하위 항목으로 비스듬히 내려가면 다른 상위 메뉴 위를 지나가는데,
       지나가는 순간 바로 바꾸면 가려던 펼침이 사라진다.
       다른 펼침이 열려 있을 때는 그 메뉴 위에 잠시 머물러야 바꾼다. */
    var switchTimer = null;
    // 닫기 예약은 메뉴마다 따로 둔다. 하나를 공유하면 옆 메뉴를 지나가며 예약이 덮여
    // 원래 메뉴의 닫기가 취소되지 않고 그대로 실행돼 펼침이 사라진다.
    var closeTimer = null;
    wrap.addEventListener('mouseenter', function () {
      clearTimeout(switchTimer);
      if (open && open !== wrap) {
        switchTimer = setTimeout(show, 280);
      } else {
        show();
      }
    });
    wrap._cancelClose = function () { clearTimeout(closeTimer); };
    wrap.addEventListener('mouseleave', function () {
      clearTimeout(switchTimer);
      // 마우스가 잠깐 벗어났다 돌아오는 경우가 잦아 여유를 둔다
      closeTimer = setTimeout(function () { hide(); }, 450);
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
