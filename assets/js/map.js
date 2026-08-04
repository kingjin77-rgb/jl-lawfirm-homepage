/* 카카오맵 렌더링
   - [data-map] 요소를 찾아 지도를 그린다.
   - SDK 미로드(도메인 미등록 등)면 아무것도 하지 않는다 → HTML 안의 "카카오맵에서 보기" 링크가 그대로 남는다.
*/
(function () {
  'use strict';

  var targets = document.querySelectorAll('[data-map]');
  if (!targets.length) return;

  if (typeof kakao === 'undefined' || !kakao.maps) {
    console.warn('[map] 카카오 SDK 미로드 — 도메인 등록 여부를 확인하세요. 지도 대신 링크가 노출됩니다.');
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
