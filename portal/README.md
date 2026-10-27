# 등기 포털 (1단계 손님 화면 + 2단계 직원 관리자 + 3단계 설문 엔진 + 4단계 보안)

기존 jllawfirm.kr 등기 시스템의 재구축. 화면 구조는 `SPEC.md` 를 따른다.
프레임워크·컴포저 없이 순수 PHP 8 + MariaDB/MySQL (가비아 공유호스팅 기준).

## 구조

```
portal/
  public/        ← 웹에 노출되는 유일한 폴더 (index.php 프런트 컨트롤러, assets)
  app/           설정·DB·인증·암호화·CSRF·페이지·뷰 (웹루트 밖)
    admin.php    2단계 직원 관리자 공용 (세션 보호·메뉴·필터·엑셀 반입)
    xlsx.php     .xlsx 파서 — 외부 라이브러리 없이 ZipArchive + SimpleXML
    pages/admin/ 관리자 페이지, views/admin/ 관리자 뷰
  tools/         make_staff.php — 직원 계정 SQL 생성 CLI (OTP 등록코드 포함)
                 integrity.php · backup.php · restore.php — 운영 도구 (4단계)
  schema.sql         전체 스키마 (1단계 기준)
  schema_phase2.sql  2단계 마이그레이션 — schema.sql 뒤에 실행 (여러 번 실행 안전)
  schema_phase3.sql  3단계 마이그레이션 — 설문 엔진 표 + sent_date (여러 번 실행 안전)
  schema_phase4.sql  4단계 마이그레이션 — 문자 인증·OTP·IP 차단 표 (여러 번 실행 안전)
  SECURITY.md    위협별 대응표 + 운영자 점검표
  seed.dev.sql   개발용 가짜 데이터
  config.sample.php  설정 파일 견본 (실제 설정은 저장소·웹루트 밖!)
```

## 2단계 — 직원 관리자 (/admin)

같은 프런트 컨트롤러(`public/index.php`)가 `/admin` 접두 경로를 별도 라우트 표로
처리한다 (admin.php 분리 대신 이 방식을 택했다 — 배포 파일이 하나라 가비아
`.htaccess` 리라이트가 그대로 먹는다). 직원 세션(`$_SESSION['staff']`)은 손님
세션과 키가 달라 서로 간섭하지 않으며, 손님 세션으로 `/admin` 에 오면
`/admin/login` 으로 보낸다.

- 상단 7메뉴(기존 그대로): 기본 정보 관리 · 인적사항 관리 · 등기진행 관리 ·
  설문 관리* · 등기접수 관리* · 위임장 관리* · 접속 통계 (* 3단계 자리표시)
- 등기진행 상세: ①인적사항 ②8단계(날짜+완료 체크) ③비용 11항목
  ④입금(차액 = 입금액 − 합계 자동) ⑤채권환불(복호화 + 환불일 입력)
  ⑥미비서류 ⑦관리자메모
- 등기진행 등록: 기존 엑셀 양식 그대로 업로드 → 미리보기 → 확정(트랜잭션,
  오류 행이 있으면 전체 반입 중단). 머리글은 1열 「순번」 행을 자동 탐지.
  (단계 완료 표시, 공동명의 옆칸, 앞자리 0 생년월일, 소수 금액 모두 처리)
- SMS: 발송 업체 연동 전 — `sms_log` 에 상태 「대기」로 저장만 한다.
- 권한: `admin` 만 직원 계정 관리 · 직원 작업기록 열람 · 세대 삭제.
- 모든 쓰기와 주소/환불 복호화 열람은 `staff_audit` 에 남는다.
- **php.ini 에 `extension=zip` 필요** (엑셀 파싱, 가비아는 기본 제공).

계정: `tools/make_staff.php` 로 SQL 을 만들어 넣는다. 진행 단계 완료 판정은
`stepN_date 있음 OR stepN_done=1` (기존 엑셀이 날짜 없이 「완료」만 적기 때문 —
`schema_phase2.sql` 참고).

미룬 것(3단계에서 완료 — 아래 「3단계」 절): 설문/등기접수/위임장 엔진,
운영 정보·팝업 설정, 엑셀 38열(발송일) 저장처.
여전히 미룬 것: SMS 실제 발송(업체 연동), 접속 통계의 유입경로·브라우저 분석.

## 3단계 — 설문·등기접수·위임장 엔진 + 운영정보·팝업 + 발송일

`schema_phase3.sql` 을 schema.sql + schema_phase2.sql 뒤에 적용한다 (여러 번 실행 안전).

- **공용 엔진** (`app/survey.php`): 설문(survey)·등기접수(accept)·위임장(attorney)
  세 메뉴가 `survey.type` 하나로 같은 표(survey/survey_target/survey_question/
  survey_response/survey_answer)와 같은 컨트롤러(`app/pages/admin/survey_*.php`)를
  쓴다. 라우터(`public/index.php`)가 경로 접두(`/admin/survey|accept|attorney`)로
  `$surveyType` 을 정한다.
- **관리자**: 목록(설문명·주최·총인원/참여/불참·작성일, 등기접수는 기간 열 추가) ·
  등록/수정(문항 빌더 — 객관식/주관식/설명글, 등기접수는 '핸드폰' 유형과
  기간·핸드폰 확인 필수 옵션 추가) · 미리보기 · 결과보기(문항별 집계) ·
  참여/불참데이타(CSV 내보내기, UTF-8 BOM) · 참여대상자(아파트 단위 —
  고른 단지 전 세대가 총인원).
- **손님**: 홈에 진행 중 건 참여 카드 → `/participate?id=N` 참여 폼
  (필수 검증 + 핸드폰 형식 검증). 세대당 1건 업서트 — 다시 제출하면 수정.
  등기접수는 기간(시작~종료, 양끝 포함) 밖이면 화면·서버 양쪽에서 차단.
- **참여자 SMS**: member_sms 와 같은 대기 저장 방식. 대상은 참여/불참/전체
  (핸드폰 있는 명의인 + 참여 세대의 제출 핸드폰, 같은 번호 1건).
  `sms_log.survey_id/audience` 로 어느 건의 어떤 대상인지 남는다.
- **운영정보·팝업** (setting 키-값): 운영 정보 설정(상담시간·점심·휴무·안내문 →
  손님 홈 「이용 안내」 카드), 팝업 관리(제목/내용/노출기간/사용여부 → 손님 홈
  로그인 직후 팝업, 기간 밖이면 자동 숨김).
- **발송일**: 엑셀 38열 → `progress.sent_date`. 반입·관리자 상세(② 단계 폼)에서
  입력하고, 손님 진행현황 화면에 발송 안내 카드로 나온다.

검증(2026-09-29): `php -l` 전 파일 통과 · schema_phase3 2회 연속 적용 멱등 확인 ·
Playwright E2E 55건 통과 (설문 등록→3유형 문항→손님 참여·수정→집계→불참→CSV,
기간 밖 차단, 위임장 목록 분리, 팝업 노출·기간종료 숨김, 발송일 반입 반영,
1440/390 가로 무넘침, 콘솔 오류 0).

## 4단계 — 보안 강화 (2026-10-27)

`schema_phase4.sql` 을 1~3단계 스키마 뒤에 적용한다 (여러 번 실행 안전). 위협별 정리는 `SECURITY.md`.

| 항목 | 내용 | 코드 |
|---|---|---|
| 손님 문자 인증 | 동·호·이름·생년월일 통과 → 그 명의인 휴대폰으로 6자리. HMAC 해시 저장 · 3분 · 5회 오입력 폐기(원자적 시도 예약) · 재발송 60초/시간당 5회. 휴대폰 없는 세대는 「등기센터 1899-4252」 카드로 차단 | `app/sms.php`, `pages/login_verify.php` |
| 문자 드라이버 | config `sms_driver` = `dev`(화면 표시+sms_log) · `aligo` · `solapi`(요청 형식까지 만든 스텁, `live=false` 동안 미발송). 운영에서 `dev` 면 부팅 거부 | `app/sms.php` |
| 직원 OTP | RFC 6238 순수 PHP(base32·HMAC-SHA1·±1 단계·같은 코드 재사용 금지). 비밀키 sodium 암호화. 첫 등록은 admin 발급 **등록코드**(1회용·24시간)가 있어야 열림. QR 은 서버 SVG(`app/qr.php`, 자체 구현·OpenCV 해독 검증). 복구코드 8개(해시·1회용). 계정 단위 5회 실패 → 15분 잠금 | `app/totp.php`, `pages/admin/otp*.php` |
| 관리자 IP 허용목록 | 보안 설정 화면(admin+OTP 재확인). 비면 미적용. 지금 IP 가 빠진 목록은 저장 거부. DB 오류 시 닫힘 | `security.php`, `pages/admin/config_security.php` |
| IP 차단 | 한 IP(IPv6 는 /64)가 10분에 서로 다른 대상 5곳 또는 30회 실패 → 1시간. 손님 로그인·문자 인증·직원 비밀번호·직원 OTP 공통 (`login_log.kind`). 허용목록 IP 는 직원 로그인에서 제외. 보안 설정에서 수동 해제 | `security.php` |
| 세션 | HttpOnly · SameSite=Strict · Secure(https, `__Host-` 이름) · strict mode. 유휴 손님 10분/직원 30분, 절대 2시간/9시간. 로그인·재인증·권한 변경 시 재발급. UA 지문이 바뀌면 폐기. 비밀번호 재설정·OTP 초기화·중지 시 `auth_version` 으로 기존 세션 즉시 끊김 | `security.php`, `admin.php` |
| 민감 작업 재인증 | 손님: 환불계좌·주소 등록/변경 전 최근 5분 문자 인증(없으면 `/verify`). 직원: CSV 내보내기·「보기」(복호화 열람)·세대 수정 화면·직원계정 관리·보안 설정 전 최근 10분 OTP(`/admin/reauth`) | `security.php` |
| 보안 헤더 | CSP(`script-src 'self' 'nonce-…'`, 인라인 핸들러 제거 → `assets/portal.js`·`admin.js` 의 `data-confirm`), HSTS(https), nosniff, Referrer, Permissions, XFO, COOP, `Cache-Control: no-store` | `security.php` |
| 업로드 | .xlsx 만 · 이름의 실행 확장자 거부 · finfo MIME · ZIP 시그니처 · xlsx 구조 · 압축률/합계(zip 폭탄) · 10MB. 웹루트 밖 임시폴더 무작위 이름, 파싱 직후 삭제(확정용으로는 암호화한 파싱 결과만 1시간) | `app/upload.php` |
| .htaccess | `public/.htaccess`: index.php 외 PHP 실행 금지·목록 금지·숨김/.sql/.md 등 차단·라우팅. `app/`·`tools/` 에도 전부 거부(이중 차단). 개발 서버 라우터도 같은 규칙 | `public/.htaccess` |
| 화면 마스킹 | 세대 목록·상세·주소·환불·설문 참여데이타의 휴대폰·계좌·주소 기본 가림. 「보기」(행/쪽 전체)는 OTP 재확인 후 원문 + `staff_audit *.reveal` | `security.php` reveal_* |
| 운영 도구 | `tools/integrity.php baseline|check` (HMAC 서명 기준선, 웹루트 새 PHP 는 웹쉘 의심 표시), `tools/backup.php` (PDO 일관 스냅숏 덤프 → gzip → secretstream 암호화, 14개 보관), `tools/restore.php` (`--decrypt-to`/`--yes`). 쉘 없으면 `/cron/integrity` · `/cron/backup` (헤더 `X-Cron-Token`) | `app/ops.php`, `app/cron.php` |
| 운영 설정 점검 | env=prod: display_errors off, http → https 301, 개발 crypto_key 재사용(SHA-256 지문)·sms_driver dev·짧은 cron_token·backup_key=crypto_key·session_timeouts 남김이면 503 | `security.php` |

설정 키 추가분은 `config.sample.php` 참고 (`sms_driver`, `sms`, `backup_key`, `backup_dir`,
`integrity_baseline`, `tmp_dir`, `session_path`, `cron_token`, `base_host`).
개발 전용 `session_timeouts` 는 env=dev 에서만 먹고, 운영 설정에 있으면 부팅 거부.

검증(2026-10-27): `php -l` 116파일 통과 · schema_phase4 2회 연속 적용 멱등 ·
Playwright E2E 112건 통과 (문자인증 성공/5회 폐기/만료/재발송 60초·시간당 5회/휴대폰 미등록,
OTP 등록코드·QR 해독·등록·재사용 거부·복구코드 1회용·계정 잠금·관리자 초기화,
IP 차단(서로 다른 세대 5곳), 유휴 만료(손님·직원), UA 바뀐 쿠키 폐기, 계좌 변경 재인증,
CSV 재인증, 마스킹/보기 기록, .php 위장·내용 위장·ZIP 내 PHP·10MB 초과 거부 + public/ 잔여 0,
IP 허용목록 자기잠금 방지·403, 웹크론 토큰, CSP 하 손님 11·관리자 41화면 콘솔 오류 0,
1440/390 무넘침, 6메뉴·엑셀 반입·설문 참여 회귀) · 동시 12건 오입력 시험에서 시도 5회로 정확히 끊김 ·
백업→변경→복원 왕복·1바이트 변조 거부 · 무결성 웹쉘 탐지·기준선 변조 탐지 ·
운영 설정(개발키·dev 드라이버 503, http→https 301).

## 로컬 개발

설정 파일: `D:\DDownloads\jl-portal-dev\config.local.php` (저장소 밖).
`crypto_key`(32바이트 base64)가 반드시 있어야 한다 — 견본 참고.

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$ini = "D:\DDownloads\jl-portal-dev\php.ini"

# 스키마 + 시드 (MariaDB 13, 127.0.0.1:3307, DB jlportal)
Get-Content portal\schema.sql        -Raw | mysql --host=127.0.0.1 --port=3307 -u jl_dev -p jlportal
Get-Content portal\schema_phase2.sql -Raw | mysql --host=127.0.0.1 --port=3307 -u jl_dev -p jlportal
Get-Content portal\schema_phase3.sql -Raw | mysql --host=127.0.0.1 --port=3307 -u jl_dev -p jlportal
Get-Content portal\schema_phase4.sql -Raw | mysql --host=127.0.0.1 --port=3307 -u jl_dev -p jlportal
Get-Content portal\seed.dev.sql      -Raw | mysql --host=127.0.0.1 --port=3307 -u jl_dev -p jlportal

# 개발 서버 (리포 루트에서)
& $php -c $ini -d upload_max_filesize=12M -d post_max_size=16M -S 127.0.0.1:8090 -t portal/public portal/public/index.php
```

개발 설정은 `sms_driver => 'dev'` 라 인증번호가 화면 노란 점선 상자에 나온다(실제 문자 없음).
직원은 첫 로그인 때 등록코드가 필요하다 — `make_staff.php` 출력 주석 줄, 또는 admin 이
「직원 계정 관리 → 등록코드 발급」.

http://127.0.0.1:8090/login 접속.
시드 계정: 테스트 스타힐스 / 101동 101호 / 홍길동 / 900101
(공동명의 시험: 101동 102호 / 김철수 850315 또는 이영희 870722)

직원 계정 만들기 (2단계 관리자용, 비밀번호 하드코딩 금지):

```powershell
& $php -c $ini portal\tools\make_staff.php admin1 관리자 admin '비밀번호10자이상'
# 출력된 SQL 을 DB 에서 실행
```

## 가비아 배포 메모

- `portal/public/` 의 내용만 도큐먼트루트(예: `html/`)에 올리고,
  `portal/app/`, `portal/tools/` 는 도큐먼트루트 **밖**(옆)에 올린다.
  `public/index.php` 의 `require .../app/bootstrap.php` 상대경로를 배치에 맞게 조정.
- 설정 파일은 도큐먼트루트 옆 `portal-config.php` (app/config.php 가 자동 탐색).
- 도큐먼트루트에 `.htaccess` 로 전 경로를 index.php 에 몰아준다:

  ```apache
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^ index.php [L]
  ```

- HTTPS 를 켜면 세션 쿠키에 secure 플래그가 자동으로 붙는다 (security.php, 이름도 `__Host-JLPORTAL`).
- `public/.htaccess` 가 저장소에 들어 있다 — 위 리라이트 + PHP 실행 제한·숨김파일 차단 포함.
- php.ini(가비아 PHP 설정): `upload_max_filesize` 12M 이상, `post_max_size` 16M 이상 (앱이 10MB 로 자른다).
- `crypto_key` 를 잃으면 저장된 주소·계좌를 복호화할 수 없다.
  설정 파일을 백업 대상에 포함하되, 저장소·DB에는 절대 넣지 않는다.

## 보안 요약

- 모든 쿼리는 prepared statement (`app/db.php` 래퍼만 사용). 예외: `tools/restore.php` 는
  자기가 만든 덤프를 그대로 실행한다(CLI 전용).
- 직원 비밀번호: argon2id (없으면 bcrypt). 5회 실패 → 15분 잠금. + OTP 2단계 (4단계).
- 손님 로그인: 노출 단지 + 동/호 + 이름(NFC 정규화·공백 제거) + 생년월일 6자리 + 문자 인증.
  세대 기준 1시간 10회 실패 → 잠금 (`login_log` 집계), IP 단위 차단 (4단계).
- 세션: HttpOnly + SameSite=Strict + 로그인 시 재발급 + 유휴/절대 만료. CSRF 토큰 전 POST 검증.
- 주소·계좌: sodium secretbox 암호화(키는 설정 파일에만). DB 유출 시에도 평문 없음.
- 작업기록: 직원 `staff_audit`, 손님 `customer_log` (값 원문은 남기지 않음).

## 2단계(관리자)로 미뤘던 것 — 완료

- /admin 전체 7메뉴 (설문·등기접수·위임장은 3단계 자리표시), FAQ 관리,
  등기진행 엑셀 업로드, refund_date 입력 — 위 「2단계」 절 참고.
- SMS 는 대기 저장까지 (실제 발송 업체 연동은 3단계).
