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
      lead: { t: '법인소개', d: '소통 · 공감 · 신뢰 세 가지 원칙으로 일합니다.' },
      items: [
        { t: '법인 개요', h: 'about.html#intro', d: '설립 배경과 운영 원칙' },
        { t: '구성원 변호사', h: 'lawyers.html', d: '구성원변호사 3인 · 소속변호사 3인' },
        { t: '지명원 내려받기', h: 'about.html#profile', d: 'PDF 회사소개서' },
        { t: '오시는 길', h: 'contact.html', d: '서초 본사무소 · 동탄 분사무소' }
      ]
    },
    'lawyers.html': {
      lead: { t: '구성원', d: '각자의 분야를 맡고, 하나의 사건에서 팀으로 만납니다.' },
      items: [
        { t: '박종일', h: 'lawyers/park-jong-il.html', d: '대표변호사 · 공인회계사' },
        { t: '이지훈', h: 'lawyers/lee-ji-hun.html', d: '구성원변호사 · 건설 · 하자' },
        { t: '임준규', h: 'lawyers/lim-jun-kyu.html', d: '구성원변호사 · 형사법 전문' },
        { t: '하혜용', h: 'lawyers/ha-hye-yong.html', d: '소속변호사 · 하자소송' },
        { t: '장우진', h: 'lawyers/jang-woo-jin.html', d: '소속변호사 · 민사 · 조세' },
        { t: '오현진', h: 'lawyers/oh-hyun-jin.html', d: '소속변호사 · 송무' }
      ]
    },
    'practice.html': {
      lead: { t: '업무분야', d: '여섯 개 분야를 나눠 맡되, 한 사건은 함께 봅니다.' },
      items: [
        { t: '재건축 · 재개발', h: 'redevelopment.html', d: '조합 운영 · 관리처분 · 현금청산 · 명도', hot: true },
        { t: '하자소송', h: 'practice.html#defect', d: '하자보수 청구 · 보증금 소송 · 담보책임기간 계산' },
        { t: '단체등기', h: 'registry.html', d: '입주 아파트 · 분양전환 · 대지권' },
        { t: '민사 · 형사', h: 'practice.html#civil', d: '부동산 · 채권회수 · 수사 초기 대응' },
        { t: '기업법무', h: 'corporate.html', d: '법률자문 · 법인등기' },
        { t: '상속 · 이혼 · 조세', h: 'practice.html#tax', d: '조세심판 · 상속재산분할 · 유류분' }
      ]
    },
    'registry.html': {
      lead: { t: '단체등기', d: '입주 단지 단위 소유권이전등기를 한 창구에서 처리합니다.' },
      items: [
        { t: '제이엘 등기센터', h: 'dongtan.html', d: '신청 · 접수 · 조회 · 계산', hot: true },
        { t: '단체등기 신청', h: 'dongtan.html#group', d: '아파트명 · 성함 · 직책 · 연락처만' },
        { t: '개별등기 접수', h: 'dongtan.html#apply', d: '등기 종류별 온라인 신청' },
        { t: '등기비용 계산기', h: 'dongtan.html#calc', d: '취득세 · 채권 · 수수료 자동 계산' },
        { t: '진행 조회', h: 'dongtan.html#track', d: '접수한 등기의 현재 단계' },
        { t: '필요 서류', h: 'dongtan.html#docs', d: '유형별 준비 서류 목록' }
      ]
    },
    'law.html': {
      lead: { t: '법률정보', d: '법제처 자료를 매일 06시에 자동으로 받아옵니다.' },
      items: [
        { t: '공동주택 관련 법령', h: 'law.html#acts', d: '주택법 · 공동주택관리법 등 12종' },
        { t: '최신 판례', h: 'law.html#cases', d: '하자 · 재건축 · 등기 쟁점' },
        { t: '질의응답', h: 'qna.html', d: '실제로 받은 질문과 답변' }
      ]
    },
    'qna.html': {
      lead: { t: '질의응답', d: '받은 질문에 담당 변호사가 답한 내용을 공개합니다.' },
      items: [
        { t: '전체 보기', h: 'qna.html', d: '분야 6종 · 검색' },
        { t: '질문 남기기', h: 'qna.html#ask', d: '법률 · 등기 · 대출 무엇이든' },
        { t: '법률정보', h: 'law.html', d: '법령 원문과 판례' }
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
        '<ul class="gnbdrop__list">' +
          conf.items.map(function (it) {
            return '<li><a href="' + esc(href(it.h)) + '"' + (it.hot ? ' class="is-hot"' : '') + '>' +
                     '<b>' + esc(it.t) + (it.hot ? '<span class="gnbdrop__tag">주력</span>' : '') + '</b>' +
                     '<small>' + esc(it.d) + '</small>' +
                   '</a></li>';
          }).join('') +
        '</ul>' +
      '</div>';
    wrap.appendChild(panel);

    function show() {
      clearTimeout(closeTimer);
      if (open && open !== wrap) hide(open);
      panel.hidden = false;
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
      closeTimer = setTimeout(function () { hide(); }, 140);
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
