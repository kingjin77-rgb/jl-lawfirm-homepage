/* 움직임 — 눈에 띄려고 넣는 것이 아니라 읽는 순서를 만들기 위해 넣는다.

   1) 숫자 세어 올리기      [data-count]  또는 .statcard .num
   2) 스크롤 진행 막대       페이지 상단
   3) 목록 순차 등장         [data-stagger] 안의 자식
   4) 제목 밑줄 그어지기     [data-underline]
   5) 히어로 시차 이동       .hero__media

   접근성: prefers-reduced-motion 이면 전부 최종 상태로 두고 끝낸다.
   숫자는 값이 정보이므로 애니메이션만 끄고 값은 그대로 보여준다.
*/
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasIO = 'IntersectionObserver' in window;

  /* ── 1) 숫자 세어 올리기 ───────────────────────────── */
  function setupCounters() {
    var nums = [].slice.call(document.querySelectorAll('[data-count], .statcard .num'));
    if (!nums.length) return;

    nums.forEach(function (el) {
      // 단위(小)는 그대로 두고 숫자 부분만 바꾼다
      var unit = el.querySelector('small');
      var raw = (el.getAttribute('data-count') ||
                 el.childNodes[0] && el.childNodes[0].nodeValue || '').trim();
      var target = parseFloat(raw.replace(/[^\d.]/g, ''));
      if (!isFinite(target)) return;

      el.__target = target;
      el.__unit = unit;
      el.__decimals = (raw.split('.')[1] || '').length;
      if (!reduced) write(el, 0);
    });

    function write(el, v) {
      var text = el.__decimals ? v.toFixed(el.__decimals)
                               : Math.round(v).toLocaleString('ko-KR');
      if (el.__unit) {
        el.textContent = text;
        el.appendChild(el.__unit);
      } else {
        el.textContent = text;
      }
    }

    function run(el) {
      if (el.__done) return;
      el.__done = true;
      if (reduced) { write(el, el.__target); return; }
      var dur = 1100, t0 = null;
      requestAnimationFrame(function step(t) {
        if (t0 === null) t0 = t;
        var p = Math.min((t - t0) / dur, 1);
        // 빠르게 올라갔다가 끝에서 잦아든다
        var e = 1 - Math.pow(1 - p, 3);
        write(el, el.__target * e);
        if (p < 1) requestAnimationFrame(step);
      });
    }

    if (!hasIO) { nums.forEach(run); return; }
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting) { run(e.target); io.unobserve(e.target); }
      });
    }, { threshold: 0.4 });
    nums.forEach(function (el) { if (isFinite(el.__target)) io.observe(el); });
  }

  /* ── 2) 스크롤 진행 막대 ───────────────────────────── */
  function setupProgress() {
    if (reduced) return;
    var bar = document.createElement('div');
    bar.className = 'scrollbarline';
    bar.innerHTML = '<i></i>';
    document.body.appendChild(bar);
    var fill = bar.firstChild, ticking = false;

    function update() {
      var h = document.documentElement.scrollHeight - window.innerHeight;
      fill.style.transform = 'scaleX(' + (h > 0 ? window.scrollY / h : 0) + ')';
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    update();
  }

  /* ── 3) 목록 순차 등장 ─────────────────────────────── */
  function setupStagger() {
    var groups = [].slice.call(document.querySelectorAll('[data-stagger]'));
    if (!groups.length) return;

    groups.forEach(function (g) {
      var kids = [].slice.call(g.children);
      var gap = parseInt(g.getAttribute('data-stagger'), 10) || 70;
      kids.forEach(function (k, i) {
        k.classList.add('stag');
        k.style.transitionDelay = (i * gap) + 'ms';
      });
      if (reduced || !hasIO) { kids.forEach(function (k) { k.classList.add('is-in'); }); return; }
      new IntersectionObserver(function (es, ob) {
        es.forEach(function (e) {
          if (!e.isIntersecting) return;
          kids.forEach(function (k) { k.classList.add('is-in'); });
          ob.disconnect();
        });
      }, { threshold: 0.15 }).observe(g);
    });
  }

  /* ── 4) 제목 밑줄 그어지기 ─────────────────────────── */
  function setupUnderline() {
    var els = [].slice.call(document.querySelectorAll('[data-underline]'));
    if (!els.length) return;
    els.forEach(function (el) { el.classList.add('uline'); });
    if (reduced || !hasIO) { els.forEach(function (el) { el.classList.add('is-in'); }); return; }
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.6 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ── 5) 히어로 시차 이동 ───────────────────────────── */
  function setupParallax() {
    if (reduced) return;
    var media = document.querySelector('.hero__media');
    var hero = document.querySelector('.hero');
    if (!media || !hero) return;
    var ticking = false;

    function update() {
      var y = window.scrollY;
      if (y < hero.offsetHeight) {
        // 배경이 본문보다 느리게 흐르면 깊이가 생긴다
        media.style.transform = 'translate3d(0,' + (y * 0.18) + 'px,0)';
      }
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
  }

  function boot() {
    setupCounters();
    setupProgress();
    setupStagger();
    setupUnderline();
    setupParallax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else { boot(); }
})();
