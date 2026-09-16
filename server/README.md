# 등기 진행 조회 서버

가비아 웹호스팅(PHP + MySQL)에 그대로 올리는 코드다.

진행 정보의 원천은 우리 직원이 만드는 엑셀이다.
대법원 인터넷등기소에는 "우리가 맡은 세대가 지금 몇 단계인지" 알려주는 공개 API가 없다.
옛 시스템도 직원이 넣은 자료를 보여준 것이고, 이 서버도 같은 구조다.

```
직원 엑셀  →  admin/tracking.html (브라우저 안에서만)  →  업로드본 JSON  →  이 서버  →  고객 조회
```

## 이름과 생년월일을 서버에 두지 않는다

조회는 "입력한 값이 맞는지" 확인만 하면 된다.
그래서 관리자 화면이 `SHA-256(소금값 | 이름 | 생년월일)` 만 내보내고, 서버는 그 해시를 비교한다.
서버가 뚫려도 이름과 생년월일 원본은 나오지 않는다.

`upload.php` 는 64자리 16진수가 아닌 값이 오면 그 줄을 버린다.
실수로 이름이 담긴 파일을 올려도 서버에 들어가지 않게 하는 빗장이다.

## 올리는 순서

**1. 웹호스팅 신청**
가비아 PHP 상품이어야 한다. ASP 상품에는 MySQL이 없다.
PHP 버전은 7.4 이상이면 된다.

**2. DB 만들기**
My가비아 → 웹호스팅 → 관리 → DB 관리에서 DB를 만들고 이름·계정·비밀번호를 받아 둔다.

**3. 표 만들기**
phpMyAdmin → SQL 탭 → `schema.sql` 내용을 붙여넣고 실행한다.

**4. 설정 파일**
`config.sample.php` 를 `config.php` 로 복사하고 빈칸을 채운다.

- `verify_salt` 는 길고 뜻 없는 문자열로 정한다. **정한 뒤에는 바꾸지 않는다.**
  바꾸면 이미 올린 해시가 전부 맞지 않게 되어 처음부터 다시 올려야 한다.
- `upload_token` 도 다른 긴 문자열로 정한다.

`config.php` 는 저장소에 올리지 않는다. 접속 정보를 메신저나 메일로 주고받지 말 것.

**5. 파일 올리기**
`server/` 안의 것을 웹 폴더 아래 `track/` 으로 올린다.

```
public_html/track/config.php
public_html/track/.htaccess
public_html/track/api/_common.php
public_html/track/api/lookup.php
public_html/track/api/upload.php
public_html/track/api/complexes.php
```

`schema.sql` 과 `config.sample.php` 는 올리지 않아도 된다.

**6. 홈페이지에 붙이기**
`assets/js/tracking.js` 첫 줄을 채운다.

```js
var ENDPOINT = 'https://www.jllawfirm.co.kr/track/api/lookup.php';
```

비어 있는 동안에는 조회를 시도하지 않고 전화 안내로 넘어간다.
도메인을 붙이는 순간에도 화면이 깨지지 않는다.

**7. 자료 올리기**
관리자 화면에서 "서버 업로드본 내보내기" 를 누른다.
이때 물어보는 확인용 문구가 `config.php` 의 `verify_salt` 와 **글자 하나까지 같아야 한다.**
다르면 아무도 조회되지 않는다.

내려받은 JSON을 올린다.

```
POST https://www.jllawfirm.co.kr/track/api/upload.php
X-Upload-Token: (config.php 의 upload_token)
Content-Type: application/json
```

## 창구

| 주소 | 하는 일 |
|---|---|
| `api/lookup.php` | 고객 조회. `{complex,dong,ho,name,birth}` 를 받아 단계를 준다 |
| `api/upload.php` | 직원 자료 반영. 업로드 열쇠가 필요하다 |
| `api/complexes.php` | 자료가 실제로 올라온 단지 목록 |

## 막아 둔 것

- **두드림 제한** — 이름과 생년월일은 찍어서 맞힐 수 있다.
  같은 곳에서 10분 안에 8번 실패하면 잠시 막는다. 옛 시스템에는 이 장치가 없었다.
- **어디가 틀렸는지 알려주지 않는다** — 알려주면 하나씩 맞춰 볼 수 있다.
- **접속 주소 제한** — 우리 홈페이지에서 온 요청만 받는다.
- **설정 파일 차단** — `.htaccess` 로 `config.php` 를 브라우저에서 못 읽게 막는다.
- **IP도 해시로만 기록** — IP 역시 개인정보다.

## 아직 확인하지 못한 것

이 코드는 작업한 PC에 PHP가 없어 **문법 검사를 돌리지 못했다.**
서버에 올린 뒤 `api/complexes.php` 를 브라우저로 먼저 열어 보라.
`{"ok":true,...}` 가 나오면 PHP와 DB 연결이 모두 정상이다.
오류가 나면 그 화면을 그대로 알려주면 고치겠다.

## 단지 조회를 닫을 때

등기가 모두 끝난 단지는 지우지 말고 닫는다.

```sql
UPDATE complexes SET is_open = 0 WHERE name = '단지명';
```

닫힌 단지를 조회하면 전화 안내가 나간다.
