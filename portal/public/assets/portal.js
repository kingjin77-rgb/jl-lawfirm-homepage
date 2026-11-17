/* 손님 화면 공용 스크립트 — CSP(script-src 'self') 때문에 인라인 핸들러 대신 여기서 묶는다. */
(function () {
  'use strict';
  // <select data-toggle-etc="대상id" data-etc-value="직접입력"> — 고른 값이 같을 때만 대상 칸을 보인다.
  document.querySelectorAll('select[data-toggle-etc]').forEach(function (sel) {
    var target = document.getElementById(sel.getAttribute('data-toggle-etc'));
    if (!target) { return; }
    var want = sel.getAttribute('data-etc-value');
    function sync() { target.hidden = sel.value !== want; }
    sel.addEventListener('change', sync);
    sync();
  });

  // 서명 패드 — <div data-sig-pad [data-required]> 안의 canvas + hidden input + .sig__clear.
  // Pointer Events 하나로 마우스·터치·펜을 받는다. 제출 때만 PNG data URI 를 hidden 에 넣고,
  // 아무것도 안 그렸으면 빈 값(필수면 제출을 막는다). 서버가 다시 검증한다.
  document.querySelectorAll('[data-sig-pad]').forEach(function (box) {
    var canvas = box.querySelector('canvas');
    var input = box.querySelector('input[type=hidden]');
    var err = box.querySelector('.sig__err');
    var ctx = canvas.getContext('2d');
    var inked = false, drawing = false, last = null, cssW = 0;

    function setup() {                     // 화면 폭이 바뀔 때만 다시 잡는다(모바일 주소창 높이 변화는 무시)
      var r = canvas.getBoundingClientRect();
      if (Math.round(r.width) === cssW) { return; }
      cssW = Math.round(r.width);
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.round(r.width * dpr);
      canvas.height = Math.round(r.height * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.lineWidth = 2.6;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = ctx.fillStyle = '#111';
      inked = false;
    }
    function pos(e) {
      var r = canvas.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    }
    function clear() {
      ctx.save();
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.restore();
      inked = false;
      input.value = '';
    }
    canvas.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      if (canvas.setPointerCapture) { canvas.setPointerCapture(e.pointerId); }
      drawing = true;
      last = pos(e);
      ctx.beginPath();
      ctx.arc(last.x, last.y, 1.3, 0, Math.PI * 2);
      ctx.fill();
      inked = true;
      if (err) { err.hidden = true; }
    });
    canvas.addEventListener('pointermove', function (e) {
      if (!drawing) { return; }
      e.preventDefault();
      var p = pos(e);
      ctx.beginPath();
      ctx.moveTo(last.x, last.y);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      last = p;
    });
    ['pointerup', 'pointercancel'].forEach(function (t) {
      canvas.addEventListener(t, function () { drawing = false; });
    });
    box.querySelector('.sig__clear').addEventListener('click', clear);
    window.addEventListener('resize', setup);
    setup();

    var form = box.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (inked) {
          input.value = canvas.toDataURL('image/png');
          return;
        }
        input.value = '';
        if (box.hasAttribute('data-required')) {
          e.preventDefault();
          if (err) { err.hidden = false; }
          box.scrollIntoView({ block: 'center' });
        }
      });
    }
  });
})();
