/* 관리자 페이지 비밀번호 게이트

   ※ 정적 사이트의 클라이언트 게이트는 보안장치가 아니라
      오입장을 막는 문턱이다. 소스를 열면 해시가 보이고, 해시가
      단순하면 복원도 어렵지 않다. 실제 권한 통제는 GitHub 토큰
      (콘텐츠 관리자)과 향후 등기포털 서버 계정이 담당한다.

   사용: <script src="../assets/js/admin-gate.js" data-gate="home"></script>
        data-gate: home(홈페이지관리자) | registry(등기센터관리자)
*/
(function () {
  'use strict';

  var me = document.currentScript;
  var kind = me.getAttribute('data-gate') || 'home';

  // SHA-256 해시 (평문을 코드에 남기지 않기 위한 최소한의 처리)
  // 직원이 화면마다 다른 번호를 외우게 하면 결국 메모지에 적어 붙인다.
  // 지금은 하나로 통일하고, 실제 권한 통제는 토큰·서버 계정으로 간다.
  var PW = '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4';
  var GATES = {
    home:     { label: '홈페이지 관리자', hash: PW },
    registry: { label: '등기센터 관리자', hash: PW }
  };
  var gate = GATES[kind] || GATES.home;
  // 통과 기록은 화면 종류와 무관하게 하나로 둔다.
  // 비밀번호가 같은데 화면마다 키를 나누면, 콘텐츠 관리 → 등기센터 관리로
  // 넘어갈 때마다 같은 번호를 또 묻게 된다. 탭을 닫으면 사라지는 건 그대로다.
  var KEY = 'jladmin.gate';

  function passed() {
    try { return sessionStorage.getItem(KEY) === gate.hash; } catch (e) { return false; }
  }
  if (passed()) return;   // 이미 통과

  // crypto.subtle 은 https 또는 localhost 에서만 열린다.
  // 사내망에서 http://192.168.x.x 로 열면 없어서, 입장 버튼이 아무 반응 없이 멈춘다.
  var canHash = !!(window.crypto && window.crypto.subtle && window.TextEncoder);

  function sha256(text) {
    var data = new TextEncoder().encode(text);
    return crypto.subtle.digest('SHA-256', data).then(function (buf) {
      return Array.prototype.map.call(new Uint8Array(buf), function (b) {
        return b.toString(16).padStart(2, '0');
      }).join('');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.createElement('div');
    wrap.setAttribute('style',
      'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;' +
      'background:#0d1220;font-family:inherit');
    wrap.innerHTML =
      '<form style="width:min(360px,90vw);background:#fff;padding:36px 32px;border-top:3px solid #0d2162">' +
        '<p style="font-size:12px;letter-spacing:.18em;color:#b08d4f;font-weight:700;margin:0">JL ADMIN</p>' +
        '<h1 style="font-size:20px;font-weight:800;color:#111;margin:8px 0 20px">' + gate.label + '</h1>' +
        '<input type="password" autocomplete="current-password" placeholder="비밀번호" ' +
          'style="width:100%;padding:13px 15px;font-size:15px;border:1px solid #e3e5e9;box-sizing:border-box">' +
        '<button type="submit" style="width:100%;margin-top:12px;padding:13px;font-size:15px;font-weight:600;' +
          'background:#0d2162;color:#fff;border:0;cursor:pointer">입장</button>' +
        '<p data-gate-msg style="min-height:18px;margin:10px 0 0;font-size:13px;color:#c0392b"></p>' +
      '</form>';
    document.body.appendChild(wrap);
    document.body.style.overflow = 'hidden';

    var input = wrap.querySelector('input');
    var msg = wrap.querySelector('[data-gate-msg]');
    input.focus();

    if (!canHash) {
      msg.textContent = '이 주소(보안 연결 아님)에서는 비밀번호를 확인할 수 없습니다. ' +
        'https:// 주소나 이 PC의 localhost 주소로 다시 열어 주세요.';
      input.disabled = true;
      wrap.querySelector('button').disabled = true;
      return;
    }

    // 엔터로도 들어갈 수 있어야 한다. 브라우저에 따라 form 의 기본 제출이
    // 일어나지 않는 경우가 있어 키 입력을 직접 받는다.
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); tryOpen(); }
    });
    wrap.querySelector('form').addEventListener('submit', function (e) {
      e.preventDefault();
      tryOpen();
    });

    function tryOpen() {
      sha256(input.value).then(function (h) {
        if (h === gate.hash) {
          try { sessionStorage.setItem(KEY, gate.hash); } catch (e) { /* 저장 불가 브라우저: 이번 화면만 연다 */ }
          wrap.remove();
          document.body.style.overflow = '';
        } else {
          msg.textContent = '비밀번호가 맞지 않습니다.';
          input.value = '';
          input.focus();
        }
      }, function () {
        msg.textContent = '비밀번호를 확인하지 못했습니다. 브라우저를 새로 고친 뒤 다시 시도해 주세요.';
      });
    }
  });
})();
