/* 상담 문의 폼 — 입력 내용을 정리해 메일 프로그램으로 넘기거나 복사한다.

   외부 서버로 전송하지 않는다. 상담 내용에는 사건 정보가 담기므로
   제3자 서비스를 거치지 않는 것이 원칙이다. 서버가 준비되면
   send() 안쪽만 바꾸면 된다.
*/
(function () {
  'use strict';

  var form = document.querySelector('[data-consult]');
  if (!form) return;

  var TO = 'jllaw2020@naver.com';
  var result = form.querySelector('[data-consult-result]');
  var area = form.querySelector('#cf-area');
  var complexWrap = form.querySelector('[data-when-registry]');

  /* 단체등기를 고르면 단지 정보 칸을 보여준다 */
  function syncComplex() {
    if (!complexWrap) return;
    var on = area && area.value === '공동주택 단체등기';
    complexWrap.hidden = !on;
  }
  if (area) area.addEventListener('change', syncComplex);
  syncComplex();

  function val(id) {
    var el = form.querySelector('#' + id);
    return el ? el.value.trim() : '';
  }

  function say(msg, kind) {
    if (!result) return;
    result.textContent = msg;
    result.className = 'cform__result' + (kind ? ' is-' + kind : '');
  }

  /* 필수 항목 확인 — 비어 있으면 그 칸으로 이동시킨다 */
  function validate() {
    var checks = [
      ['cf-name', '성함을 입력해 주세요.'],
      ['cf-tel', '연락처를 입력해 주세요.'],
      ['cf-msg', '상담 내용을 입력해 주세요.']
    ];
    for (var i = 0; i < checks.length; i++) {
      if (!val(checks[i][0])) {
        var el = form.querySelector('#' + checks[i][0]);
        say(checks[i][1], 'bad');
        if (el) el.focus();
        return false;
      }
    }
    var agree = form.querySelector('#cf-agree');
    if (agree && !agree.checked) {
      say('개인정보 수집·이용에 동의해 주셔야 문의를 접수할 수 있습니다.', 'bad');
      agree.focus();
      return false;
    }
    return true;
  }

  function subject() {
    var a = val('cf-area');
    return '[상담문의] ' + (a ? a + ' · ' : '') + val('cf-name');
  }

  function body() {
    var lines = [
      '성함     : ' + val('cf-name'),
      '연락처   : ' + val('cf-tel')
    ];
    if (val('cf-email')) lines.push('이메일   : ' + val('cf-email'));
    if (val('cf-office')) lines.push('희망사무소: ' + val('cf-office'));
    if (val('cf-area')) lines.push('상담분야 : ' + val('cf-area'));
    if (complexWrap && !complexWrap.hidden && val('cf-complex')) {
      lines.push('단지정보 : ' + val('cf-complex'));
    }
    lines.push('');
    lines.push('[상담 내용]');
    lines.push(val('cf-msg'));
    lines.push('');
    lines.push('— 개인정보 수집·이용에 동의함');
    return lines.join('\n');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validate()) return;

    var url = 'mailto:' + TO +
      '?subject=' + encodeURIComponent(subject()) +
      '&body=' + encodeURIComponent(body());

    /* mailto 는 길이 제한이 있는 환경이 있다. 너무 길면 복사를 권한다. */
    if (url.length > 1800) {
      say('내용이 길어 메일 프로그램으로 넘기기 어렵습니다. "내용 복사"를 눌러 붙여넣어 주세요.', 'bad');
      return;
    }

    window.location.href = url;
    say('메일 프로그램을 열었습니다. 내용을 확인하신 뒤 보내기를 눌러주세요. ' +
        '열리지 않으면 "내용 복사"를 이용해 주세요.', 'ok');
  });

  var copyBtn = form.querySelector('[data-consult-copy]');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      if (!validate()) return;
      var text = subject() + '\n\n' + body() + '\n\n받는 곳: ' + TO;

      function fallback() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
        document.body.removeChild(ta);
        say(ok ? '복사했습니다. ' + TO + ' 으로 붙여넣어 보내주세요.'
               : '복사하지 못했습니다. 내용을 직접 선택해 복사해 주세요.', ok ? 'ok' : 'bad');
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          say('복사했습니다. ' + TO + ' 으로 붙여넣어 보내주세요.', 'ok');
        }, fallback);
      } else {
        fallback();
      }
    });
  }
})();
