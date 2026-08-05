/* 변호사 슬라이드쇼 — data/lawyers.json
   1인 단독 화면을 자동으로 전환한다.
   - 자동 재생(기본 7초), 호버/포커스 시 정지
   - 탭·화살표·키보드(←/→)로 수동 이동
   - prefers-reduced-motion 이면 전환 간격을 늘리고 진행바 애니메이션만 끈다
*/
(function () {
  'use strict';

  var root = document.querySelector('[data-lawyer-show]');
  if (!root) return;

  var stage = root.querySelector('[data-lw-stage]');
  var nav = root.querySelector('[data-lw-tabs]');
  var curEl = root.querySelector('[data-lw-cur]');
  var totEl = root.querySelector('[data-lw-total]');
  var bar = root.querySelector('.lwshow__bar');

  var esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };
  // 경력 항목에는 <strong> 만 허용한다
  var careerHtml = function (s) {
    return esc(s).replace(/&lt;(\/?)strong&gt;/gi, '<$1strong>');
  };

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var list = [];
  var idx = 0;
  var timer = null;
  var interval = 7000;

  function render(items) {
    stage.innerHTML = items.map(function (p, i) {
      var photo = p.photo
        ? '<img src="' + esc(p.photo) + '" alt="' + esc(p.name) + ' 변호사">'
        : '<span class="initial">' + esc(p.initial || p.name.charAt(0)) + '</span>';
      // 개인 페이지가 있으면 사진·이름을 클릭해 이동할 수 있다
      var page = p.slug ? 'lawyers/' + esc(p.slug) + '.html' : '';
      var photoBox = page
        ? '<a class="lwslide__photolink" href="' + page + '" aria-label="' + esc(p.name) + ' 변호사 프로필 보기"><div class="lwslide__photo">' + photo + '</div></a>'
        : '<div class="lwslide__photo">' + photo + '</div>';
      var nameHtml = esc(p.name) + (p.en ? '<span class="en">' + esc(p.en) + '</span>' : '');
      return '' +
      '<article class="lwslide' + (i === 0 ? ' is-on' : '') + '" data-i="' + i + '"' +
              ' role="tabpanel" aria-label="' + esc(p.name) + ' 변호사">' +
        photoBox +
        '<div class="lwslide__body">' +
          '<p class="lwslide__role">' + esc(p.role) + '</p>' +
          '<h3 class="lwslide__name">' +
            (page ? '<a href="' + page + '">' + nameHtml + '</a>' : nameHtml) + '</h3>' +
          (p.tagline ? '<p class="lwslide__tagline">' + esc(p.tagline) + '</p>' : '') +
          '<ul class="lwslide__career">' +
            (p.career || []).map(function (c) { return '<li>' + careerHtml(c) + '</li>'; }).join('') +
          '</ul>' +
          (page
            ? '<p class="lwslide__more"><a class="btn" href="' + page + '">프로필 전체 보기 <span class="arrow">→</span></a></p>'
            : '') +
        '</div>' +
      '</article>';
    }).join('');

    nav.innerHTML = items.map(function (p, i) {
      return '<button type="button" class="lwshow__tab' + (i === 0 ? ' is-on' : '') + '" data-go="' + i + '"' +
             ' role="tab" aria-selected="' + (i === 0) + '">' +
             esc(p.name) + '<small>' + esc(p.short || p.role) + '</small></button>';
    }).join('');

    if (totEl) totEl.textContent = items.length;
    if (curEl) curEl.textContent = 1;
  }

  function show(n) {
    if (!list.length) return;
    idx = (n + list.length) % list.length;
    stage.querySelectorAll('.lwslide').forEach(function (el, i) {
      el.classList.toggle('is-on', i === idx);
    });
    nav.querySelectorAll('.lwshow__tab').forEach(function (el, i) {
      el.classList.toggle('is-on', i === idx);
      el.setAttribute('aria-selected', String(i === idx));
    });
    if (curEl) curEl.textContent = idx + 1;
    restartBar();
  }

  function restartBar() {
    if (!bar || reduced) return;
    var i = bar.querySelector('i');
    root.classList.remove('is-playing');
    void i.offsetWidth;          // 애니메이션 리셋
    if (timer) root.classList.add('is-playing');
  }

  function play() {
    if (timer) return;
    timer = setInterval(function () { show(idx + 1); }, reduced ? interval * 2 : interval);
    root.classList.add('is-playing');
    restartBar();
  }

  function pause() {
    if (!timer) return;
    clearInterval(timer);
    timer = null;
    root.classList.remove('is-playing');
  }

  /* 조작 */
  nav.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-go]');
    if (!b) return;
    show(parseInt(b.dataset.go, 10));
  });

  root.querySelectorAll('[data-lw-move]').forEach(function (b) {
    b.addEventListener('click', function () {
      show(idx + parseInt(b.dataset.lwMove, 10));
    });
  });

  root.addEventListener('mouseenter', pause);
  root.addEventListener('mouseleave', play);
  root.addEventListener('focusin', pause);
  root.addEventListener('focusout', function (e) {
    if (!root.contains(e.relatedTarget)) play();
  });

  root.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') { e.preventDefault(); show(idx - 1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); show(idx + 1); }
  });

  // 탭이 백그라운드면 재생 중단 (불필요한 타이머 방지)
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) pause(); else play();
  });

  fetch('data/lawyers.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      list = d.lawyers || [];
      if (!list.length) throw new Error('구성원 데이터가 비어 있습니다');
      interval = d.interval || 7000;
      root.style.setProperty('--dur', (interval / 1000) + 's');
      render(list);
      play();
    })
    .catch(function (err) {
      console.error('[lawyers]', err);
      stage.innerHTML = '<div class="board-empty">구성원 정보를 불러오지 못했습니다. (' +
                        esc(err.message) + ')</div>';
    });
})();
