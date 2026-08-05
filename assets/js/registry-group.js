/* 단체등기 신청(협의회 단위) + 진행조회 단지 목록 — dongtan.html

   단체등기는 개별 접수와 달리 아파트명·성함·직책·연락처만 받는다.
   목적이 전화 상담 연결이기 때문이다. 전송 원칙은 상담폼과 동일 —
   외부 서버를 거치지 않는다.

   진행조회 단지 목록은 data/registry.json 의 tracking.complexes 를
   채워 넣는다. 기존 시스템(jllawfirm.kr)의 단지 코드와 같다.
*/
(function () {
  'use strict';

  var TO = 'jllaw2020@naver.com';

  /* ---------- 진행조회 단지 목록 ---------- */
  var sel = document.querySelector('#tGroup');
  if (sel) {
    fetch('data/registry.json', { cache: 'no-cache' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var t = d.tracking;
        if (!t || !t.complexes) return;
        t.complexes.forEach(function (c) {
          var o = document.createElement('option');
          o.value = c.id;
          o.textContent = c.name;
          sel.appendChild(o);
        });
        var form = document.querySelector('[data-track-form]');
        if (form && t.action) form.setAttribute('action', t.action);
      })
      .catch(function () { /* 목록을 못 채워도 폼 자체는 동작한다 */ });
  }

  /* ---------- 단체등기 상담 신청 ---------- */
  var root = document.querySelector('[data-group-apply]');
  if (!root) return;

  function val(id) {
    var el = root.querySelector('#' + id);
    return el ? el.value.trim() : '';
  }
  function say(msg, kind) {
    var el = root.querySelector('[data-group-msg]');
    if (!el) return;
    el.textContent = msg;
    el.className = 'cform__result' + (kind ? ' is-' + kind : '');
  }

  root.querySelector('[data-group-send]').addEventListener('click', function () {
    if (!val('gComplex')) { say('아파트명을 입력해 주세요.', 'bad'); root.querySelector('#gComplex').focus(); return; }
    if (!val('gName')) { say('성함을 입력해 주세요.', 'bad'); root.querySelector('#gName').focus(); return; }
    if (!val('gTel')) { say('연락처를 입력해 주세요.', 'bad'); root.querySelector('#gTel').focus(); return; }
    var agree = root.querySelector('#gAgree');
    if (agree && !agree.checked) {
      say('개인정보 수집·이용에 동의해 주셔야 신청할 수 있습니다.', 'bad');
      agree.focus();
      return;
    }

    var body = [
      '[단체등기 상담 신청 — 입주예정자협의회]',
      '아파트  : ' + val('gComplex'),
      '성함    : ' + val('gName') + (val('gRole') ? ' (' + val('gRole') + ')' : ''),
      '연락처  : ' + val('gTel'),
      '',
      '— 개인정보 수집·이용에 동의함'
    ].join('\n');

    var url = 'mailto:' + TO +
      '?subject=' + encodeURIComponent('[단체등기 신청] ' + val('gComplex')) +
      '&body=' + encodeURIComponent(body);
    window.location.href = url;
    say('메일 프로그램을 열었습니다. 보내기를 누르시면 접수됩니다. 급하시면 1899-4252 로 바로 전화 주세요.', 'ok');
  });
})();
