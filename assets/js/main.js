/* 법무법인 제이엘 — main.js
   - 헤더 스크롤 상태
   - 모바일 내비 토글
   - 스크롤 리빌
   - 히어로 슬라이드 크로스페이드
*/
(function () {
  'use strict';

  var header = document.querySelector('.header');
  var toggle = document.querySelector('.nav-toggle');
  var body = document.body;

  /* ---------- 헤더: 스크롤하면 흰 배경 ---------- */
  if (header && !header.classList.contains('is-sub')) {
    var onScroll = function () {
      header.classList.toggle('is-solid', window.scrollY > 40);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------- 모바일 내비 ---------- */
  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
    document.querySelectorAll('.gnb a').forEach(function (a) {
      a.addEventListener('click', function () {
        body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ---------- 스크롤 리빌 ----------
     동적으로 추가된 요소도 살릴 수 있도록 window.JLReveal 로 노출한다.
     (매거진 카드처럼 JSON에서 그려지는 콘텐츠가 여기에 해당) */
  var io = null;
  if ('IntersectionObserver' in window) {
    io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var delay = parseInt(el.dataset.delay || '0', 10);
        setTimeout(function () { el.classList.add('is-in'); }, delay);
        io.unobserve(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
  }

  window.JLReveal = function (nodes) {
    var list = nodes || document.querySelectorAll('.reveal');
    Array.prototype.forEach.call(list, function (el) {
      if (io) io.observe(el);
      else el.classList.add('is-in');
    });
  };

  window.JLReveal();

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- 히어로 슬라이드 (7초 체류 / 2초 크로스페이드) ---------- */
  var slides = document.querySelectorAll('.hero__slide');
  var curEl = document.querySelector('[data-hero-current]');
  var totEl = document.querySelector('[data-hero-total]');
  var slideTimer = null;
  if (totEl && slides.length) totEl.textContent = slides.length;
  if (slides.length > 1 && !reduced) {
    var idx = 0;
    slideTimer = setInterval(function () {
      slides[idx].classList.remove('is-active');
      idx = (idx + 1) % slides.length;
      slides[idx].classList.add('is-active');
      // 영상이 재생 중이면 화면에 보이는 것은 영상이다. 숫자는 영상 쪽이 맡는다
      if (curEl && !document.querySelector('.hero__video.is-ready')) curEl.textContent = idx + 1;
    }, 7000);
  }

  /* ---------- 주요업무 아코디언 (hover + click/focus) ---------- */
  document.querySelectorAll('[data-acc]').forEach(function (acc) {
    var items = Array.prototype.slice.call(acc.querySelectorAll('.acc__item'));
    var open = function (item) {
      items.forEach(function (i) { i.classList.toggle('is-open', i === item); });
    };
    items.forEach(function (item) {
      item.tabIndex = 0;
      item.addEventListener('mouseenter', function () { open(item); });
      item.addEventListener('click', function () { open(item); });
      item.addEventListener('focus', function () { open(item); });
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(item); }
      });
    });
  });

  /* ---------- 히어로 배경 영상 ----------
     파일이 없거나 재생 실패하면 이미지 슬라이드가 그대로 유지된다.
     데이터 절약 모드 · 모션 최소화 설정에서는 로드하지 않는다.
     휴대폰은 첫 영상(도시) 한 편만 받는다 — 세 편을 다 받으면 데이터가 아깝다. */
  var videos = [].slice.call(document.querySelectorAll('.hero__video'));
  if (window.innerWidth <= 768) videos = videos.slice(0, 1);
  if (videos.length && !reduced &&
      !(navigator.connection && navigator.connection.saveData)) {
    var ready = [];   // 실제로 재생되는 것만 모은다. 없는 파일은 조용히 빠진다
    var at = 0;
    var turn = null;
    var counter = document.querySelector('[data-hero-current]');
    var total = document.querySelector('[data-hero-total]');

    function label() {
      if (total) total.textContent = ready.length || 1;
      if (counter) counter.textContent = ready.length ? at + 1 : 1;
    }

    function step() {
      if (ready.length < 2) return;
      var prev = ready[at];
      at = (at + 1) % ready.length;
      var v = ready[at];
      // 다음 영상을 먼저 위로 올려 덮은 뒤 이전 것을 내린다.
      // 동시에 바꾸면 둘 다 반투명해지는 순간이 생겨 화면이 한 번 어두워진다.
      v.currentTime = 0;
      v.style.zIndex = 1;
      v.classList.add('is-ready');
      var p = v.play();
      if (p && p.catch) p.catch(function () {});
      label();
      setTimeout(function () {
        prev.classList.remove('is-ready');
        prev.style.zIndex = '';
        prev.pause();          // 안 보이는 영상까지 돌리면 노트북 배터리만 먹는다
      }, 1500);
    }

    function joined(v) {
      var shown = ready[at];
      ready.push(v);
      // 먼저 불러와진 순서가 아니라 적어 둔 순서(도시 → 빌딩 → 서류)대로 돌린다
      ready.sort(function (a, b) { return videos.indexOf(a) - videos.indexOf(b); });
      if (shown) at = ready.indexOf(shown);
      if (ready.length === 1) {
        // 첫 번째로 준비된 것을 바로 띄운다.
        // 이때부터 화면에 보이는 것은 영상이므로 뒤에 깔린 이미지 슬라이드는 멈춘다.
        v.classList.add('is-ready');
        if (slideTimer) { clearInterval(slideTimer); slideTimer = null; }
        var p = v.play();
        if (p && p.catch) p.catch(function () { v.classList.remove('is-ready'); });
      } else {
        v.pause();   // 차례가 올 때까지 멈춰 둔다
      }
      label();
      // 두 개 이상 모이면 그때부터 돌린다
      if (ready.length === 2 && !turn) turn = setInterval(step, 9000);
    }

    /* 첫 영상(도시)부터 받고, 그것이 재생 준비되면 나머지를 받는다.
       한꺼번에 받으면 용량이 작은 영상이 먼저 떠서 순서가 뒤바뀐다. */
    function fetchVideo(v) {
      var src = v.dataset.src;
      if (!src) return;
      v.addEventListener('canplay', function () { joined(v); }, { once: true });
      v.addEventListener('error', function () { v.classList.remove('is-ready'); });
      v.src = src;
      v.load();
    }
    var first = videos[0];
    var rest = videos.slice(1);
    var restStarted = false;
    function startRest() {
      if (restStarted) return;
      restStarted = true;
      rest.forEach(fetchVideo);
    }
    first.addEventListener('canplay', startRest, { once: true });
    first.addEventListener('error', startRest, { once: true });
    setTimeout(startRest, 4000);   // 첫 영상이 늦어도 나머지는 받는다
    fetchVideo(first);
  }

  /* ---------- 글자 단위 스태거 리빌 ---------- */
  document.querySelectorAll('[data-split]').forEach(function (root) {
    var order = 0;
    root.querySelectorAll('.split-line').forEach(function (line) {
      var walk = function (node) {
        Array.prototype.slice.call(node.childNodes).forEach(function (child) {
          if (child.nodeType === 3) {
            var frag = document.createDocumentFragment();
            child.nodeValue.split('').forEach(function (ch) {
              var span = document.createElement('span');
              span.className = 'split-char';
              span.textContent = ch;
              span.style.transitionDelay = (order * 28) + 'ms';
              order++;
              frag.appendChild(span);
            });
            node.replaceChild(frag, child);
          } else if (child.nodeType === 1) {
            walk(child);
          }
        });
      };
      walk(line);
    });

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        root.querySelectorAll('.split-char').forEach(function (c) { c.classList.add('is-in'); });
      });
    });
  });

  /* ---------- 매거진 카테고리 필터 ---------- */
  var magFilter = document.querySelector('[data-magfilter]');
  var magList = document.querySelector('[data-maglist]');
  if (magFilter && magList) {
    magFilter.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-filter]');
      if (!btn) return;
      var cat = btn.dataset.filter;
      magFilter.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
      magList.querySelectorAll('.magcard').forEach(function (card) {
        var show = cat === 'all' || card.dataset.cat === cat;
        card.style.display = show ? '' : 'none';
      });
    });
  }

  /* ---------- 현재 페이지 GNB 표시 ---------- */
  var here = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.gnb a').forEach(function (a) {
    var href = (a.getAttribute('href') || '').split('/').pop();
    if (href && href === here) a.setAttribute('aria-current', 'page');
  });

  /* ---------- 카카오톡 상담 ----------
     PC 브라우저 안에서 카카오톡 대화를 여는 기능은 카카오가 주지 않는다.
     채널 채팅 주소를 열면 카카오가 손님의 카카오톡(PC 앱·휴대폰)에 대화방을 띄우고
     브라우저에는 안내문만 남긴다. 큰 빈 탭이 뜨면 고장 난 줄 알므로
     PC 에서는 카카오 공식 방식처럼 작은 창으로 연다. 휴대폰은 그대로 앱으로 넘어간다. */
  var KAKAO_CHAT = 'https://pf.kakao.com/_xlDxdhs/chat';
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href="' + KAKAO_CHAT + '"]');
    if (!a) return;
    var pc = window.matchMedia('(hover: hover) and (pointer: fine)').matches && window.innerWidth > 768;
    if (!pc) return;
    var w = window.open(KAKAO_CHAT, 'jl_kakao_chat',
      'width=460,height=620,left=' + Math.max(0, (screen.width - 460) / 2) + ',top=' + Math.max(0, (screen.height - 620) / 2));
    if (w) { e.preventDefault(); w.focus(); }   // 팝업이 막히면 링크대로 새 탭으로 연다
  });
})();
