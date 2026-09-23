/* 기본 정보 관리 — 법인 정보와 단지 목록
 *
 * 옛 시스템에서는 단지를 "아파트 관리" 로 따로 등록했다.
 * 여기서는 엑셀을 올리면 단지가 저절로 생기지만,
 * 엑셀이 오기 전에 조회만 먼저 열어 둘 때를 위해 직접 추가도 둔다.
 */
(function (W) {
  'use strict';
  var JL = W.JL, ui = JL.ui, esc = JL.esc, $ = JL.$;

  JL.mod('settings', {
    title: '기본 정보',
    icon: '⚙',
    lead: '법인 정보와 관리하는 단지를 정합니다.',

    render: function (el) {
      var st = JL.db.settings;
      var names = JL.complexNames();

      el.innerHTML =
        '<section class="of-panel">' +
          '<h3 class="of-h3">법인 정보</h3>' +
          '<p class="of-p">고객 조회 화면과 문자 안내에 들어가는 이름과 번호입니다.</p>' +
          '<div class="of-form">' +
            '<label class="of-fld"><span>법인명</span><input id="stFirm" value="' + esc(st.firm) + '"></label>' +
            '<label class="of-fld"><span>등기센터 대표번호</span><input id="stTel" value="' + esc(st.tel) + '"></label>' +
            '<label class="of-fld"><span>문자 발신번호</span><input id="stSender" value="' + esc(st.sender) + '" placeholder="통신사에 사전 등록한 번호만 쓸 수 있습니다"></label>' +
          '</div>' +
          '<div class="of-acts"><button type="button" class="btn btn--fill" id="stSave">법인 정보 반영</button></div>' +
        '</section>' +

        '<section class="of-panel">' +
          '<div class="of-row"><h3 class="of-h3">단지</h3><span class="of-sp"></span>' +
            '<button type="button" class="btn btn--fill" id="cxAdd">단지 추가</button></div>' +
          '<p class="of-p">조회를 닫으면 고객 조회에서 그 단지가 사라지고 전화 안내가 나갑니다. 등기가 모두 끝난 단지에 씁니다.</p>' +
          ui.table([
            { t: '단지명', f: function (n) { return '<b>' + esc(n) + '</b>'; } },
            { t: '세대', cls: 'num', f: function (n) { return Object.keys(JL.db.complexes[n].units).length.toLocaleString(); } },
            { t: '고객 조회', f: function (n) {
                var o = JL.db.complexes[n].open;
                return '<span class="of-pill ' + (o ? 'ok' : 'mute') + '">' + (o ? '열림' : '닫힘') + '</span>';
            } },
            { t: '조회 링크', f: function (n) {
                return '<button type="button" class="btn sm" data-link="' + esc(n) + '">문자용 링크 복사</button>';
            } },
            { t: '', f: function (n) {
                var o = JL.db.complexes[n].open;
                return '<button type="button" class="btn sm" data-toggle="' + esc(n) + '">' + (o ? '조회 닫기' : '조회 열기') + '</button> ' +
                  '<button type="button" class="btn sm danger" data-del="' + esc(n) + '">삭제</button>';
            } }
          ], names, { empty: '아직 단지가 없습니다. 단지를 추가하거나 등기진행에서 엑셀을 올리십시오.' }) +
        '</section>';

      $('stSave').addEventListener('click', function () {
        st.firm = $('stFirm').value.trim();
        st.tel = $('stTel').value.trim();
        st.sender = JL.phone($('stSender').value) || $('stSender').value.trim();
        JL.touch();
        ui.toast('법인 정보를 반영했습니다.', 'ok');
      });

      $('cxAdd').addEventListener('click', function () {
        ui.dialog({
          title: '단지 추가',
          body: '<label class="of-fld"><span>단지명</span><input id="cxName" placeholder="예: 검단 롯데캐슬 넥스티엘"></label>' +
            '<p class="of-note">고객 조회 화면의 아파트 목록에 이 이름 그대로 나옵니다.</p>',
          ok: '추가',
          onOk: function () {
            var n = $('cxName').value.trim();
            if (!n) return '단지명을 입력해 주십시오.';
            if (JL.db.complexes[n]) return '이미 있는 단지입니다.';
            JL.complex(n);
            JL.touch();
            ui.drawComplexes(n);
            ui.go('settings');
            ui.toast(n + ' 단지를 추가했습니다.', 'ok');
            return true;
          }
        });
      });

      el.querySelectorAll('[data-toggle]').forEach(function (b) {
        b.addEventListener('click', function () {
          var c = JL.db.complexes[b.dataset.toggle];
          c.open = !c.open;
          JL.touch(); ui.drawComplexes(); ui.go('settings');
        });
      });

      el.querySelectorAll('[data-del]').forEach(function (b) {
        b.addEventListener('click', function () {
          var n = b.dataset.del, cnt = Object.keys(JL.db.complexes[n].units).length;
          // 이 단지를 대상으로 한 설문은 단지가 사라지면 열 수 없다. 같이 지운다.
          var svs = JL.db.surveys.filter(function (s) { return s.complex === n; });
          var nResp = svs.reduce(function (a, s) { return a + s.responses.length; }, 0);
          ui.dialog({
            title: '단지 삭제',
            body: '<p class="of-p"><b>' + esc(n) + '</b> 와 세대 ' + cnt.toLocaleString() +
              '개를 지웁니다. 되돌릴 수 없습니다.</p>' +
              (svs.length
                ? '<div class="of-callout warn"><b>이 단지 설문 ' + svs.length + '건과 응답 ' + nResp.toLocaleString() + '건도 함께 지웁니다.</b><br>' +
                  '결과가 필요하면 먼저 설문 화면에서 CSV 로 받아 두십시오.</div>'
                : '') +
              '<label class="of-fld"><span>확인을 위해 단지명을 그대로 입력하십시오</span><input id="delName"></label>',
            ok: '지우기',
            onOk: function () {
              if ($('delName').value.trim() !== n) return '단지명이 다릅니다.';
              delete JL.db.complexes[n];
              JL.db.surveys = JL.db.surveys.filter(function (s) { return s.complex !== n; });
              if (JL.smsTarget && JL.smsTarget.complex === n) JL.smsTarget = null;
              JL.touch(); ui.drawComplexes(); ui.go('settings');
              ui.toast(n + ' 단지를 지웠습니다' + (svs.length ? ' · 설문 ' + svs.length + '건도 지웠습니다' : '') + '.', 'ok');
              return true;
            }
          });
        });
      });

      el.querySelectorAll('[data-link]').forEach(function (b) {
        b.addEventListener('click', function () {
          var url = JL.trackUrl(b.dataset.link);
          var done = function () { ui.toast('링크를 복사했습니다. 문자에 붙여 넣으십시오.', 'ok'); };
          if (navigator.clipboard) navigator.clipboard.writeText(url).then(done, function () { prompt('이 링크를 복사하십시오', url); });
          else prompt('이 링크를 복사하십시오', url);
        });
      });
    }
  });

})(window);
