/* 지도 렌더링 — 2단 구성
   1) 기본: OpenStreetMap 임베드 iframe. 키·도메인 등록이 필요 없어 어디서든 바로 뜬다.
   2) 승격: 카카오 SDK가 로드되면(= 개발자 콘솔에 해당 도메인이 등록되면)
      카카오맵으로 교체한다. 국내 지도는 카카오 쪽이 상세하다.
   카카오 미등록 상태에서는 SDK 요청이 401(domain mismatched)로 거부되므로 1)이 그대로 남는다.
*/
(function () {
  'use strict';

  var targets = document.querySelectorAll('[data-map]');
  if (!targets.length) return;

  /* ---------- 1) OSM 임베드 ---------- */
  function osmEmbed(el) {
    var lat = parseFloat(el.dataset.lat);
    var lng = parseFloat(el.dataset.lng);
    if (isNaN(lat) || isNaN(lng)) return;

    var d = 0.0032; // 표시 범위(약 350m)
    var bbox = [lng - d, lat - d * 0.62, lng + d, lat + d * 0.62].join('%2C');

    var frame = document.createElement('iframe');
    frame.src = 'https://www.openstreetmap.org/export/embed.html?bbox=' + bbox +
                '&layer=mapnik&marker=' + lat + '%2C' + lng;
    frame.loading = 'lazy';
    frame.title = (el.dataset.label || '사무소') + ' 위치';
    frame.setAttribute('aria-label', frame.title);
    frame.style.cssText = 'width:100%;height:100%;border:0;display:block';

    el.innerHTML = '';
    el.style.position = 'relative';
    el.appendChild(frame);
    addOverlay(el);
  }

  /* 라벨 + 길찾기 버튼 (두 방식 공통) */
  function addOverlay(el) {
    var label = el.dataset.label;
    if (label) {
      var tag = document.createElement('span');
      tag.textContent = label;
      tag.style.cssText =
        'position:absolute;left:12px;top:12px;z-index:3;padding:7px 12px;' +
        'background:#0d2162;color:#fff;font-size:13px;font-weight:600;white-space:nowrap;' +
        'box-shadow:0 6px 18px rgba(8,21,65,.28);pointer-events:none';
      el.appendChild(tag);
    }
    var link = el.dataset.link;
    if (link) {
      var a = document.createElement('a');
      a.href = link;
      a.target = '_blank';
      a.rel = 'noopener';
      a.textContent = '카카오맵 길찾기 ↗';
      a.style.cssText =
        'position:absolute;right:12px;bottom:12px;z-index:3;padding:8px 14px;' +
        'background:rgba(255,255,255,.94);color:#0d2162;font-size:13px;font-weight:600;' +
        'box-shadow:0 4px 14px rgba(0,0,0,.16)';
      el.appendChild(a);
    }
  }

  targets.forEach(osmEmbed);

  /* ---------- 2) 카카오 SDK가 살아있으면 승격 ---------- */
  if (typeof kakao === 'undefined' || !kakao.maps) {
    console.info('[map] 카카오 SDK 미로드 — OpenStreetMap으로 표시합니다. ' +
                 '카카오 개발자 콘솔에 이 도메인을 등록하면 카카오맵으로 자동 전환됩니다.');
    return;
  }

  kakao.maps.load(function () {
    targets.forEach(function (el) {
      var lat = parseFloat(el.dataset.lat);
      var lng = parseFloat(el.dataset.lng);
      if (isNaN(lat) || isNaN(lng)) return;

      var link = el.dataset.link;
      el.innerHTML = '';

      var pos = new kakao.maps.LatLng(lat, lng);
      var map = new kakao.maps.Map(el, { center: pos, level: 4 });

      // 확대/축소 컨트롤
      map.addControl(new kakao.maps.ZoomControl(), kakao.maps.ControlPosition.RIGHT);
      // 스크롤은 지도 위에서 페이지가 멈추지 않도록 기본 비활성 (클릭 후 활성화)
      map.setZoomable(false);
      el.addEventListener('click', function () { map.setZoomable(true); }, { once: true });

      new kakao.maps.Marker({ map: map, position: pos });

      var label = el.dataset.label || '';
      if (label) {
        new kakao.maps.CustomOverlay({
          map: map,
          position: pos,
          yAnchor: 2.2,
          content:
            '<div style="padding:7px 12px;background:#0d2162;color:#fff;font-size:13px;' +
            'font-weight:600;white-space:nowrap;border-radius:2px;' +
            'box-shadow:0 6px 18px rgba(8,21,65,.28)">' + label + '</div>'
        });
      }

      // 카카오맵 바로가기 버튼
      if (link) {
        var a = document.createElement('a');
        a.href = link;
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = '길찾기 ↗';
        a.style.cssText =
          'position:absolute;left:12px;bottom:12px;z-index:3;padding:8px 14px;' +
          'background:rgba(255,255,255,.94);color:#0d2162;font-size:13px;font-weight:600;' +
          'box-shadow:0 4px 14px rgba(0,0,0,.16)';
        el.style.position = 'relative';
        el.appendChild(a);
      }

      // 리사이즈 시 중심 유지
      window.addEventListener('resize', function () {
        map.relayout();
        map.setCenter(pos);
      });
    });
  });
})();
