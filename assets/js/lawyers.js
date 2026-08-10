/* 변호사 슬라이드쇼 — data/lawyers.json
   1인 단독 화면을 자동으로 전환한다.
   - 화면에 들어왔을 때부터 자동 재생 (스크롤로 내려오면 항상 첫 번째부터)
   - 호버/포커스 시 정지
   - 탭·화살표·키보드(←/→)로 수동 이동
   - prefers-reduced-motion 이면 전환 간격을 늘리고 진행바 애니메이션만 끈다
*/
(function () {
  'use strict';

  var root = document.querySelector('[data-lawyer-show]');
  if (!root) return;

  var stage = root.querySelector('[data-lw-stage]');
  var nav = root.querySelector('[data-lw-tabs]');
  // 구성원변호사 / 소속변호사 명단 — 슬라이드와 같은 데이터를 등급별로 다시 묶어 보여준다
  var roster = document.querySelector('[data-lw-roster]');
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

  // 등급 표기 — 법무법인은 구성원변호사(파트너)와 소속변호사를 구분해 표기한다
  var TIERS = [
    { key: '구성원', label: '구성원변호사', mod: 'partner',
      desc: '법무법인의 지분을 가진 파트너 변호사입니다. 사건의 최종 책임을 집니다.' },
    { key: '소속', label: '소속변호사', mod: 'associate',
      desc: '법무법인에 소속되어 담당 분야 사건을 수행합니다.' }
  ];
  function tierOf(p) {
    for (var i = 0; i < TIERS.length; i++) if (TIERS[i].key === p.tier) return TIERS[i];
    return TIERS[TIERS.length - 1];
  }

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var list = [];
  var idx = 0;
  var timer = null;
  var interval = 7000;
  var inView = false;   // 화면 밖에서는 돌리지 않는다

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
          '<p class="lwslide__role">' +
            '<span class="lwtier lwtier--' + tierOf(p).mod + '">' + esc(p.short || tierOf(p).label) + '</span>' +
            (p.role ? '<span class="lwslide__qual">' + esc(p.role) + '</span>' : '') + '</p>' +
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
      return '<button type="button" class="lwshow__tab is-' + tierOf(p).mod + (i === 0 ? ' is-on' : '') +
             '" data-go="' + i + '" role="tab" aria-selected="' + (i === 0) + '">' +
             esc(p.name) + '<small>' + esc(p.short || tierOf(p).label) + '</small></button>';
    }).join('');

    if (totEl) totEl.textContent = items.length;
    if (curEl) curEl.textContent = 1;
  }

  /* 등급별 명단 — 슬라이드 순서와 별개로 구성원변호사 · 소속변호사를 나눠 보여준다 */
  function renderRoster(items) {
    if (!roster) return;
    roster.innerHTML = TIERS.map(function (t) {
      var group = items.filter(function (p) { return tierOf(p).key === t.key; });
      if (!group.length) return '';
      return '' +
      '<div class="lwgrade lwgrade--' + t.mod + '">' +
        '<div class="lwgrade__head">' +
          '<h3>' + esc(t.label) + '<span class="lwgrade__n">' + group.length + '명</span></h3>' +
          '<p>' + esc(t.desc) + '</p>' +
        '</div>' +
        '<ul class="lwgrade__list">' +
          group.map(function (p) {
            var page = p.slug ? 'lawyers/' + esc(p.slug) + '.html' : '';
            var photo = p.photo
              ? '<img src="' + esc(p.photo) + '" alt="" loading="lazy">'
              : '<span class="initial">' + esc(p.initial || p.name.charAt(0)) + '</span>';
            var inner =
              '<span class="lwgrade__ph">' + photo + '</span>' +
              '<span class="lwgrade__txt">' +
                '<b>' + esc(p.name) + '</b>' +
                '<small>' + esc(p.short || t.label) + (p.role ? ' · ' + esc(p.role) : '') + '</small>' +
              '</span>';
            return '<li>' + (page
              ? '<a href="' + page + '">' + inner + '<span class="lwgrade__go">→</span></a>'
              : '<span class="lwgrade__no">' + inner + '</span>') + '</li>';
          }).join('') +
        '</ul>' +
      '</div>';
    }).join('');
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
    if (timer || !inView) return;
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

  /* 화면에 보일 때만 돌린다.
     스크롤해서 내려오는 동안 대표변호사가 지나가 버리는 것을 막기 위해,
     처음 들어온 시점에 첫 번째 슬라이드부터 다시 시작한다. */
  var seen = false;
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        inView = e.isIntersecting;
        if (inView) {
          if (!seen) { seen = true; if (list.length) show(0); }
          play();
        } else {
          pause();
        }
      });
    }, { threshold: 0.35 }).observe(root);
  } else {
    inView = true;   // 미지원 브라우저는 종전대로
  }

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
      renderRoster(list);
      play();   // 이미 화면 안이면 즉시 시작, 아니면 관찰자가 깨운다
    })
    .catch(function (err) {
      console.error('[lawyers]', err);
      stage.innerHTML = '<div class="board-empty">구성원 정보를 불러오지 못했습니다. (' +
                        esc(err.message) + ')</div>';
    });
})();
