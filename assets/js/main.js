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

  /* ---------- 스크롤 리빌 ---------- */
  var targets = document.querySelectorAll('.reveal');
  if (targets.length) {
    if (!('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          var delay = parseInt(el.dataset.delay || '0', 10);
          setTimeout(function () { el.classList.add('is-in'); }, delay);
          io.unobserve(el);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
      targets.forEach(function (el) { io.observe(el); });
    }
  }

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- 히어로 슬라이드 (7초 체류 / 2초 크로스페이드) ---------- */
  var slides = document.querySelectorAll('.hero__slide');
  var curEl = document.querySelector('[data-hero-current]');
  var totEl = document.querySelector('[data-hero-total]');
  if (totEl && slides.length) totEl.textContent = slides.length;
  if (slides.length > 1 && !reduced) {
    var idx = 0;
    setInterval(function () {
      slides[idx].classList.remove('is-active');
      idx = (idx + 1) % slides.length;
      slides[idx].classList.add('is-active');
      if (curEl) curEl.textContent = idx + 1;
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
     모바일 · 데이터 절약 모드 · 모션 최소화 설정에서는 로드하지 않는다. */
  var video = document.querySelector('.hero__video');
  if (video && !reduced && window.innerWidth > 768 &&
      !(navigator.connection && navigator.connection.saveData)) {
    var src = video.dataset.src;
    if (src) {
      video.addEventListener('canplay', function () { video.classList.add('is-ready'); }, { once: true });
      video.addEventListener('error', function () { video.classList.remove('is-ready'); });
      video.src = src;
      video.load();
      var p = video.play();
      if (p && p.catch) p.catch(function () { video.classList.remove('is-ready'); });
    }
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
})();
