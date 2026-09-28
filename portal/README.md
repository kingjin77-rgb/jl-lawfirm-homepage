# 등기 포털 (1단계 — 손님 화면)

기존 jllawfirm.kr 등기 시스템의 재구축. 화면 구조는 `SPEC.md` 를 따른다.
프레임워크·컴포저 없이 순수 PHP 8 + MariaDB/MySQL (가비아 공유호스팅 기준).

## 구조

```
portal/
  public/        ← 웹에 노출되는 유일한 폴더 (index.php 프런트 컨트롤러, assets)
  app/           설정·DB·인증·암호화·CSRF·페이지·뷰 (웹루트 밖)
  tools/         make_staff.php — 직원 계정 SQL 생성 CLI
  schema.sql     전체 스키마 (2단계 관리자까지 포함해 설계)
  seed.dev.sql   개발용 가짜 데이터
  config.sample.php  설정 파일 견본 (실제 설정은 저장소·웹루트 밖!)
```

## 로컬 개발

설정 파일: `D:\DDownloads\jl-portal-dev\config.local.php` (저장소 밖).
`crypto_key`(32바이트 base64)가 반드시 있어야 한다 — 견본 참고.

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$ini = "D:\DDownloads\jl-portal-dev\php.ini"

# 스키마 + 시드 (MariaDB 13, 127.0.0.1:3307, DB jlportal)
Get-Content portal\schema.sql   -Raw | mysql --host=127.0.0.1 --port=3307 -u jl_dev -p jlportal
Get-Content portal\seed.dev.sql -Raw | mysql --host=127.0.0.1 --port=3307 -u jl_dev -p jlportal

# 개발 서버 (리포 루트에서)
& $php -c $ini -S 127.0.0.1:8090 -t portal/public portal/public/index.php
```

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

- HTTPS 를 켜면 세션 쿠키에 secure 플래그가 자동으로 붙는다 (bootstrap.php).
- `crypto_key` 를 잃으면 저장된 주소·계좌를 복호화할 수 없다.
  설정 파일을 백업 대상에 포함하되, 저장소·DB에는 절대 넣지 않는다.

## 보안 요약

- 모든 쿼리는 prepared statement (`app/db.php` 래퍼만 사용).
- 직원 비밀번호: argon2id (없으면 bcrypt). 5회 실패 → 15분 잠금.
- 손님 로그인: 노출 단지 + 동/호 + 이름(NFC 정규화·공백 제거) + 생년월일 6자리.
  세대 기준 1시간 10회 실패 → 잠금 (`login_log` 집계).
- 세션: httponly + SameSite=Lax + 로그인 시 재발급. CSRF 토큰 전 POST 검증.
- 주소·계좌: sodium secretbox 암호화(키는 설정 파일에만). DB 유출 시에도 평문 없음.
- 작업기록: 직원 `staff_audit`, 손님 `customer_log` (값 원문은 남기지 않음).

## 2단계(관리자)로 미룬 것

- /admin 전체 (기본정보·인적사항·등기진행·설문·등기접수·위임장·접속통계 7메뉴)
- SMS 발송 (`sms_log` 테이블은 준비됨), 등기진행 엑셀 업로드(샘플파일 열 구조 확정 필요)
- refund_request.refund_date 입력(직원), FAQ 관리 화면
