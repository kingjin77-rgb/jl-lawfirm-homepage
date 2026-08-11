/* 담보책임기간 계산기 — 업무분야 > 하자소송

   사용검사일과 하자 유형을 넣으면 하자보수 청구가 가능한 기한을 계산한다.
   기간은 공동주택관리법 시행령 별표의 구분을 따르며, data 속성으로만
   관리하므로 법령이 바뀌면 HTML 의 option 값만 고치면 된다.

   ※ 기산점은 사안에 따라 사용검사일이 아니라 인도일이 되기도 하고,
      같은 균열이라도 마감재인지 구조체인지에 따라 기간이 달라진다.
      결과에 참고용임을 함께 표시한다.
*/
(function () {
  'use strict';

  var form = document.querySelector('[data-warranty]');
  if (!form) return;

  var dateEl = form.querySelector('#wDate');
  var typeEl = form.querySelector('#wType');
  var out = form.querySelector('[data-warranty-out]');

  function fmt(d) {
    return d.getFullYear() + '년 ' + (d.getMonth() + 1) + '월 ' + d.getDate() + '일';
  }

  function calc() {
    var raw = dateEl.value;
    if (!raw) {
      out.innerHTML = '<p class="warr__empty">사용검사일을 넣으면 청구 기한이 나옵니다.</p>';
      return;
    }
    var opt = typeEl.options[typeEl.selectedIndex];
    var years = parseInt(typeEl.value, 10);

    // 'YYYY-MM-DD' 를 그냥 넘기면 세계표준시 자정으로 읽는다. 우리 시각과 9시간이 어긋나
    // 하루가 밀려 계산되므로, 시각을 붙여 이 컴퓨터 시각의 자정으로 읽게 한다.
    var start = new Date(raw + 'T00:00:00');
    var end = new Date(raw + 'T00:00:00');
    end.setFullYear(end.getFullYear() + years);

    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var left = Math.floor((end - today) / 86400000);

    var state, cls;
    if (left < 0) {
      state = '기간이 ' + Math.abs(left).toLocaleString('ko-KR') + '일 지났습니다';
      cls = 'is-over';
    } else if (left === 0) {
      state = '오늘이 마지막 날입니다';
      cls = 'is-soon';
    } else if (left <= 180) {
      state = '남은 기간 ' + left.toLocaleString('ko-KR') + '일 — 서둘러야 합니다';
      cls = 'is-soon';
    } else {
      state = '남은 기간 ' + left.toLocaleString('ko-KR') + '일';
      cls = 'is-ok';
    }

    out.innerHTML =
      '<div class="warr__result ' + cls + '">' +
        '<p class="warr__label">' + opt.textContent.split('—')[0].trim() + ' · ' + years + '년</p>' +
        '<p class="warr__date">' + fmt(end) + ' 까지</p>' +
        '<p class="warr__state">' + state + '</p>' +
      '</div>' +
      '<p class="warr__note">' +
        '사용검사일 ' + fmt(start) + ' 기준입니다. 전유부분은 인도일부터 기산하는 경우가 있고, ' +
        '같은 균열이라도 마감재인지 구조체인지에 따라 기간이 달라집니다. ' +
        '<b>참고용 계산이며 실제 청구 가능 여부는 사안별로 확인이 필요합니다.</b>' +
      '</p>';
  }

  dateEl.addEventListener('change', calc);
  dateEl.addEventListener('input', calc);
  typeEl.addEventListener('change', calc);
  calc();
})();
