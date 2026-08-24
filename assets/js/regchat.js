/* 등기센터 상담 채팅 — 제이엘 등기센터(동탄) 전용

   이 페이지에는 이미 접수·조회·계산기·서류 네 가지 도구가 있는데,
   처음 온 사람은 자기 상황이 어디에 해당하는지를 먼저 모른다.
   그래서 대화로 물어보고 해당 도구까지 데려다주는 역할만 한다.

   서버가 없다. 전부 브라우저 안에서 끝나고, 입력한 내용은 어디로도 전송되지 않는다.
   실제 접수는 기존 접수 위저드(#apply)가, 통화는 대표번호가 받는다.
*/
(function () {
  'use strict';

  if (!/dongtan\.html$/.test(location.pathname)) return;

  var TEL = '1899-4252';
  var TEL_HREF = 'tel:18994252';

  /* 대화 시나리오.
     go: 이동할 섹션 / say: 답변 문단 / next: 이어지는 선택지 */
  var NODES = {
    start: {
      say: ['등기센터입니다. 어떤 것부터 도와드릴까요?'],
      next: ['track', 'cost', 'docs', 'apply', 'group', 'call']
    },
    track: {
      label: '진행 상황이 궁금해요',
      say: ['<b>진행 조회</b>에서 바로 확인하실 수 있습니다.',
            '아파트·동·호수와 계약자 성함, 생년월일을 넣으면 접수부터 등기필증 수령까지 어느 단계인지 나옵니다.',
            '조회가 안 되면 아직 접수 전이거나 성함이 계약자와 다른 경우가 많습니다.'],
      go: '#track', goLabel: '진행 조회 열기',
      next: ['call', 'start']
    },
    cost: {
      say: ['<b>등기비용 계산기</b>에 취득 원인과 조건을 넣으면 예상 비용이 나옵니다.',
            '취득세, 국민주택채권(할인율은 매일 아침 자동 갱신), 부대비용까지 함께 계산됩니다.',
            '생애최초 감면이나 공동명의처럼 조건이 걸린 경우에는 계산 결과와 실제가 달라질 수 있어, 접수 때 알려주시면 다시 확인해 드립니다.'],
      go: '#calc', goLabel: '비용 계산기 열기',
      next: ['docs', 'call', 'start']
    },
    docs: {
      label: '어떤 서류가 필요한가요',
      say: ['등기 종류에 따라 다릅니다. 아래에서 골라 주세요.'],
      next: ['docs_new', 'docs_conv', 'docs_land', 'start']
    },
    docs_new: {
      label: '입주 아파트',
      say: ['신축 입주라면 소유권이전등기와, 대출이 있으면 근저당권설정등기를 함께 접수합니다.',
            '<b>준비서류 체크리스트</b>에서 조건을 고르면 필요한 서류만 추려서 보여드립니다.'],
      go: '#docs', goLabel: '체크리스트 열기',
      next: ['apply', 'call', 'start']
    },
    docs_conv: {
      label: '분양전환',
      say: ['임대로 살던 집을 분양전환해 소유권을 취득하는 경우입니다.',
            'LH·리츠·민간임대에 따라 절차와 서류가 달라 확인이 필요합니다.',
            '유형별 차이는 <b>단체등기 상세안내</b>에 정리해 두었습니다.'],
      go: '#docs', goLabel: '체크리스트 열기',
      link: { href: 'registry-detail.html#conversion', text: '분양전환 유형별 안내 보기' },
      next: ['apply', 'call', 'start']
    },
    docs_land: {
      label: '대지권',
      say: ['건물은 등기됐는데 대지권이 미등기로 남은 경우입니다.',
            '토지 정산이나 환지·합필 같은 선행 절차가 얽혀 있는 경우가 많아, 원인을 먼저 확인해야 합니다.'],
      go: '#docs', goLabel: '체크리스트 열기',
      link: { href: 'registry-detail.html#land-rights', text: '대지권 등기 안내 보기' },
      next: ['call', 'start']
    },
    apply: {
      label: '접수하고 싶어요',
      say: ['<b>개별등기 온라인 접수</b>에서 다섯 단계로 접수하실 수 있습니다.',
            '등기 종류를 고르고 정보를 넣으시면, 담당자가 확인한 뒤 당일 중 연락드립니다.'],
      go: '#apply', goLabel: '접수 시작하기',
      next: ['docs', 'call', 'start']
    },
    group: {
      label: '단지 전체 단체등기예요',
      say: ['세대 단위가 아니라 단지 단위라면 접수 창구가 다릅니다.',
            '입주예정자협의회 단위 접수는 <b>아파트친구</b>에서 받고, 담당 변호사가 연락드립니다.'],
      go: '#group', goLabel: '단체등기 안내 보기',
      next: ['call', 'start']
    },
    call: {
      label: '담당자와 통화할래요',
      say: ['등기센터 대표번호는 <b>' + TEL + '</b> 입니다.',
            '평일 09:00 – 18:00 (점심 12:00 – 13:00)에 받습니다.',
            '단지명과 동·호수를 미리 알려주시면 확인이 빠릅니다.'],
      tel: true,
      next: ['start']
    }
  };

  var LABELS = {
    track: '진행 상황이 궁금해요',
    cost: '비용이 얼마나 나오나요',
    docs: '어떤 서류가 필요한가요',
    apply: '접수하고 싶어요',
    group: '단지 전체 단체등기예요',
    call: '담당자와 통화할래요',
    docs_new: '입주 아파트',
    docs_conv: '분양전환',
    docs_land: '대지권',
    start: '처음으로'
  };

  /* ---------- DOM ---------- */
  var root = document.createElement('div');
  root.className = 'rchat';
  root.innerHTML =
    '<button type="button" class="rchat__fab" aria-expanded="false">' +
      '<span class="rchat__fabi" aria-hidden="true"></span>등기 상담' +
    '</button>' +
    '<section class="rchat__box" hidden aria-label="등기 상담">' +
      '<header class="rchat__head">' +
        '<div><strong>제이엘 등기센터</strong><span>평일 09:00 – 18:00</span></div>' +
        '<button type="button" class="rchat__x" aria-label="닫기">&#10005;</button>' +
      '</header>' +
      '<div class="rchat__log" role="log" aria-live="polite"></div>' +
      '<div class="rchat__opts"></div>' +
      '<p class="rchat__note">안내용 도우미입니다. 입력한 내용은 저장·전송되지 않습니다.</p>' +
    '</section>';
  document.body.appendChild(root);
  document.body.classList.add('has-rchat');

  var fab = root.querySelector('.rchat__fab');
  var box = root.querySelector('.rchat__box');
  var log = root.querySelector('.rchat__log');
  var opts = root.querySelector('.rchat__opts');

  function bubble(html, who) {
    var d = document.createElement('div');
    d.className = 'rchat__b rchat__b--' + (who || 'bot');
    d.innerHTML = html;
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
    return d;
  }

  function goTo(sel) {
    var el = document.querySelector(sel);
    if (!el) return;
    close();
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    el.classList.add('is-flash');
    setTimeout(function () { el.classList.remove('is-flash'); }, 1600);
  }

  function render(key) {
    var n = NODES[key];
    if (!n) return;

    if (key !== 'start') bubble(LABELS[key] || key, 'me');

    n.say.forEach(function (s) { bubble(s); });

    if (n.go) {
      var b = bubble('<button type="button" class="rchat__go">' +
                     (n.goLabel || '해당 화면으로') + ' &#8594;</button>');
      b.querySelector('button').addEventListener('click', function () { goTo(n.go); });
    }
    if (n.link) {
      bubble('<a class="rchat__go" href="' + n.link.href + '">' +
             n.link.text + ' &#8594;</a>');
    }
    if (n.tel) {
      bubble('<a class="rchat__go" href="' + TEL_HREF + '">' + TEL + ' 전화하기</a>');
    }

    opts.innerHTML = '';
    (n.next || []).forEach(function (k) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = LABELS[k] || k;
      btn.addEventListener('click', function () { render(k); });
      opts.appendChild(btn);
    });
  }

  function open() {
    box.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
    root.classList.add('is-open');
    if (!log.children.length) render('start');
  }
  function close() {
    box.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
    root.classList.remove('is-open');
  }

  fab.addEventListener('click', function () {
    if (box.hidden) open(); else close();
  });
  root.querySelector('.rchat__x').addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !box.hidden) close();
  });
})();
