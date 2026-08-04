# 법무법인 제이엘 — 홈페이지 리뉴얼

정적 HTML/CSS/JS. 빌드 도구·프레임워크 없음. 파일만 웹서버에 올리면 동작.

## 로컬 미리보기

```bash
python -m http.server 5180 --directory jl-homepage
```

http://localhost:5180

## 구조

```
jl-homepage/
├── index.html        메인 (영상 히어로 + 소개 + 업무분야 + 구성원 + 단체등기 + CTA)
├── about.html        법인소개 (소통·공감·신뢰, 법인 개요)
├── lawyers.html      구성원 5인 프로필
├── practice.html     업무분야 상세 + 소송절차 개관
├── corporate.html    기업법무 + 자문 고객사
├── registry.html     단체등기 6단계 절차 + 준비서류
├── contact.html      오시는 길 (서울 본사 / 동탄 분사무소)
└── assets/
    ├── css/style.css
    ├── js/main.js
    └── video/hero.mp4   ← 임시 생성본. 실사 영상으로 교체 대상
```

## 디자인 기준

레퍼런스 종합 — raumlawfirm(풀스크린 무채색 섹션), suhnlaw(Pretendard·48px 볼드·국영문 병기 타이틀),
lawl(시네마틱 크로스페이드 히어로 + 글자단위 타이포 리빌).

| 토큰 | 값 |
|---|---|
| 서체 | Pretendard Variable (CDN) |
| 잉크 | `#0d0f12` |
| 배경 보조 | `#f5f6f8` |
| 포인트 | 골드 `#a8874b` |
| 컨테이너 | 1280px |
| 섹션 여백 | `clamp(72px, 9vw, 140px)` |

## 히어로 동작

1. `assets/video/hero.mp4` 로드 성공 → 영상이 배경으로 재생 (음소거·루프·인라인)
2. 파일 없음 / 재생 실패 / 모바일(≤768px) / 데이터 절약 모드 / `prefers-reduced-motion`
   → 이미지 슬라이드 3장 크로스페이드(7초 체류, 2초 전환, 켄번스 슬로줌)로 자동 대체
3. 타이틀은 글자 단위로 28ms씩 지연되며 아래에서 떠오름

영상 교체: `assets/video/hero.mp4` 덮어쓰기. 권장 1920×1080, 10–15초 루프, 무음, 3MB 이하(H.264).

## 교체 대기 항목

- [ ] 히어로 실사 영상 (현재 ffmpeg 임시 생성본)
- [ ] 히어로 이미지 슬라이드 (현재 CSS 그라디언트, `.hero__slide--1~3`)
- [ ] 변호사 프로필 사진 (현재 성씨 한자 플레이스홀더 — `.lawyer__photo` 안에 `<img>` 넣으면 대체)
- [ ] 카카오맵/네이버지도 iframe (`contact.html` → `.office__map`)
- [ ] 상담게시판·자료실 (동적 기능 필요, 현재 미포함)

## 기존 사이트 대비 변경점

- 반응형 전면 적용 (기존 사이트는 고정폭)
- 페이지 7개로 재편, GNB 6개 항목으로 정리
- 콘텐츠는 기존 사이트 원문 유지, 구조·표기만 정리
- 대출 관련 섹션 없음

## 유의

- 자문 고객사·변호사 경력은 기존 사이트 게재 내용 그대로 옮긴 것. 게시 전 최신 여부 확인 필요.
- 로그인/게시판 등 기존 `jllawfirm.kr` 회원 기능은 이번 범위에 미포함.
