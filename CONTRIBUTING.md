# 홈페이지 공동작업 가이드

법무법인 제이엘 홈페이지 작업 규칙입니다.
**코드를 몰라도 콘텐츠는 바꿀 수 있습니다.** 아래 A안부터 보세요.

---

## A. 코드 없이 콘텐츠만 바꾸기 — 관리자 페이지

매거진 발행호와 검토보고서 목록은 **관리자 페이지**에서 편집합니다.

```
사이트주소/admin/
```

1. **GitHub 연결** — 저장소, 브랜치, 토큰을 한 번만 입력해두면 브라우저에 저장됩니다.
   - 토큰 발급: GitHub → Settings → Developer settings → Personal access tokens →
     **Fine-grained tokens** → 저장소 선택 → Repository permissions에서 **Contents: Read and write**
   - 유효기간은 90일 이내로 잡고, 만료되면 다시 발급하세요.
   - **공용 PC에서는 쓰지 마세요.** 자리를 뜰 때는 "연결 해제"를 누르면 토큰이 지워집니다.
2. **편집** — 보고서 추가/수정/순서변경/삭제, 발행호 정보 수정
3. **GitHub에 저장** — 누르면 `data/magazine.json` 이 커밋되고 배포가 이어집니다

연결이 어려우면 **파일로 내려받기** → 받은 `magazine.json` 을 담당자에게 전달해도 됩니다.

### 관리자 페이지로 못 바꾸는 것

| 내용 | 편집 위치 |
|---|---|
| 변호사 프로필 | `data/lawyers.json` |
| 법률정보 목록 | 자동 수집 (매일 06:00) — 수동 편집 대상 아님 |
| 페이지 문구·구조 | 각 `*.html` |
| 색상·여백 | `assets/css/style.css` |

---

## B. 파일을 직접 고치기

### 브랜치

`main` 에 직접 커밋하지 않습니다. 항상 브랜치를 만들고 PR로 합칩니다.

```bash
git switch -c content/2026-08-매거진3호   # 콘텐츠
git switch -c feat/상담폼                  # 기능 추가
git switch -c fix/모바일-메뉴              # 버그 수정
```

접두어 — `content/` 콘텐츠, `feat/` 기능, `fix/` 수정, `design/` 디자인

### 로컬 확인

```bash
python -m http.server 5180
```

http://localhost:5180 에서 확인한 뒤 커밋합니다. **확인 없이 올리지 않습니다.**

### 커밋 메시지

무엇을 왜 바꿨는지 한국어로 씁니다. 제목 한 줄, 필요하면 본문에 이유.

```
매거진 3호 등록

표지와 목차를 넣고 최신호를 3호로 교체했다.
2호는 지난 호 목록으로 내렸다.
```

### PR

```bash
git push -u origin <브랜치명>
gh pr create
```

PR 본문에는 **바뀐 화면 스크린샷**을 넣어주세요. 리뷰가 훨씬 빨라집니다.

---

## B-2. 여러 사람이 각자 Claude 로 작업할 때

각자 자기 계정의 Claude 로 같은 홈페이지를 고칠 수 있습니다.
다만 **같은 파일을 동시에 고치면 충돌**하므로 아래 순서를 지켜주세요.

### 처음 한 번만 하는 준비

1. 저장소 초대를 수락합니다 (관리자가 GitHub 아이디로 초대합니다).
2. 자기 PC에 내려받습니다.

```bash
git clone https://github.com/kingjin77-rgb/jl-lawfirm-homepage.git
```

3. 그 폴더에서 Claude 를 엽니다. Claude Code 웹(claude.ai/code)에서
   저장소를 연결해도 됩니다.

### 작업할 때마다 반복하는 순서

**작업 시작 전 — 최신 내용부터 받습니다.** 이걸 건너뛰면 충돌이 납니다.

```bash
git switch main
git pull
git switch -c content/내작업이름
```

**작업이 끝나면 — 올리고 합칩니다.**

```bash
git push -u origin content/내작업이름
gh pr create
```

### 지켜야 할 것

| 원칙 | 이유 |
|---|---|
| **`main` 에 직접 커밋하지 않습니다** | 되돌리기 어렵고 다른 사람 작업을 덮습니다 |
| **작업 전 반드시 `git pull`** | 남이 올린 내용 위에서 시작해야 충돌이 없습니다 |
| **한 브랜치는 한 가지 일만** | 리뷰가 쉽고 문제가 생겨도 그 부분만 되돌립니다 |
| **누가 어느 파일을 맡을지 미리 나눕니다** | 같은 파일을 둘이 고치면 합칠 때 손이 많이 갑니다 |
| **오래 붙들지 않습니다** | 브랜치를 며칠씩 두면 그만큼 충돌 가능성이 커집니다 |

### Claude 에게 시킬 때 덧붙이면 좋은 말

Claude 는 지시가 구체적일수록 정확합니다. 작업을 시킬 때
**어느 파일을 고칠지**와 **main 에 직접 올리지 말 것**을 함께 말해주세요.

```
qna.html 의 답변 3건만 수정해줘.
다른 파일은 건드리지 말고, main 말고 새 브랜치에서 작업해줘.
```

### 충돌이 났을 때

`git pull` 이나 PR 에서 충돌(conflict)이 뜨면 혼자 해결하려 하지 마시고
관리자에게 알려주세요. 잘못 합치면 남의 작업이 사라집니다.

### 이렇게까지 안 해도 되는 경우

매거진 등록, 질의응답 답변, 채권 할인율 같은 **콘텐츠만 바꿀 거라면
A안(관리자 페이지)이 훨씬 간단합니다.** 브랜치도 PR도 필요 없고,
저장 버튼 한 번이면 반영됩니다.

---

## C. 파일 구조 — 어디를 고쳐야 하나

```
├── index.html          메인
├── about.html          법인소개 · 지명원 다운로드
├── lawyers.html        구성원 (슬라이드쇼)
├── practice.html       업무분야
├── corporate.html      기업법무
├── registry.html       단체등기 안내
├── dongtan.html        단체등기센터 (동탄 · 원스톱 포털)
├── law.html            법률정보 (자동 수집)
├── magazine.html       제이엘 매거진
├── contact.html        오시는 길
├── admin/              관리자 페이지
├── data/               콘텐츠 데이터 ← 여기를 주로 고칩니다
│   ├── magazine.json   매거진 · 검토보고서
│   ├── lawyers.json    변호사 프로필
│   └── law-feed.json   법률정보 (자동 생성 · 손대지 마세요)
├── docs/               배포용 문서 (지명원 PDF 등)
├── magazine/           매거진 본문 HTML
├── tools/              수집 스크립트
└── assets/
    ├── css/style.css   전체 스타일
    ├── js/             기능별 스크립트
    ├── img/            이미지
    └── video/          히어로 영상
```

---

## D. 자주 하는 작업

### 검토보고서 추가
관리자 페이지 → "보고서 추가" → 제목·설명·태그 입력 → 저장

### 매거진 새 호 발행
1. 발행기(`magazine-publisher.html`)로 HTML 생성
2. `magazine/issue-NN.html` 로 저장
3. 표지를 `assets/img/magazine/issue-NN-cover.jpg` 로 저장
4. `data/magazine.json` 의 `issues` 배열 **맨 앞에** 새 호 추가 (맨 앞이 최신호)

### 변호사 추가·수정
`data/lawyers.json` 편집. `career` 항목에는 `<strong>` 만 쓸 수 있습니다.

### 사진 교체
- 변호사 사진 — `lawyers.json` 의 `photo` 에 경로 입력 (비우면 성씨 한자 표시)
- 히어로 영상 — `assets/video/hero.mp4` 덮어쓰기 (1600×900 이상, 10–15초, 무음, 3MB 이하)

### 스타일 수정 후 반영이 안 될 때
브라우저 캐시 때문입니다. HTML의 `?v=3` 숫자를 올리세요.

```bash
python tools/bump_assets.py 4
```

---

## E. 하지 말 것

- `main` 직접 커밋
- `data/law-feed.json` 수동 편집 — 자동 갱신에 덮어써집니다
- 토큰을 코드나 커밋에 넣기
- 다른 사이트 이미지·영상 무단 사용 — 히어로 영상은 Pexels 라이선스로 확인된 것만 씁니다
- 확인 안 하고 배포

---

## F. 막혔을 때

| 증상 | 확인 |
|---|---|
| 지도가 안 보임 | 카카오 개발자 콘솔에 도메인 등록 필요. 미등록이면 OSM 지도로 표시됨 |
| 법률정보가 비어 있음 | Actions 탭 → "법률정보 피드 갱신" 실행 이력 확인. `LAW_GO_KR_OC` 시크릿 필요 |
| 관리자 저장 실패 | 토큰 만료 또는 Contents 권한 없음. "연결 확인"으로 진단 |
| 매거진이 안 뜸 | `data/magazine.json` 문법 오류. JSON 검사기로 확인 |
