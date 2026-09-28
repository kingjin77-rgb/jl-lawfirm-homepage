-- =====================================================================
-- 법무법인 제이엘 등기 포털 — 전체 스키마
-- 1단계(손님 포털)에서 실제로 쓰는 테이블과, 2단계(직원 관리자)까지
-- 내다보고 미리 잡아둔 테이블을 함께 정의한다.
-- 문자셋은 전부 utf8mb4 (이름·메모에 이모지가 들어와도 깨지지 않게).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 직원 계정 — 기존 시스템의 공용 admin 1계정을 직원별 계정으로 바꾼다.
-- 5회 실패 시 15분 잠금: failed_count / locked_until 로 구현.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login_id      VARCHAR(50)  NOT NULL,
  name          VARCHAR(50)  NOT NULL,
  role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  pass_hash     VARCHAR(255) NOT NULL,            -- password_hash() 결과 (argon2id 우선)
  failed_count  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL DEFAULT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_login (login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 직원 작업기록 — 누가 언제 무엇을 바꿨는지. 2단계 관리자 화면의 모든
-- 쓰기 작업이 여기로 남는다. 1단계에서는 헬퍼만 만들어 둔다.
CREATE TABLE IF NOT EXISTS staff_audit (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id    INT UNSIGNED NULL,                  -- NULL = 시스템 작업
  action      VARCHAR(60)  NOT NULL,              -- 예: 'household.update', 'cost.save'
  target      VARCHAR(190) NOT NULL DEFAULT '',   -- 예: 'household:123'
  detail      TEXT NULL,                          -- 바뀐 내용 요약(민감정보 원문은 넣지 않는다)
  ip          VARCHAR(45)  NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_staff (staff_id, created_at),
  KEY ix_audit_target (target),
  CONSTRAINT fk_audit_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 아파트(단지) — 기존 '아파트 관리'의 그룹. 단지별 수납계좌를 함께 둔다.
-- exposed=1 인 단지만 손님 로그인 화면의 select 에 나온다.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complex (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  exposed      TINYINT(1) NOT NULL DEFAULT 1,
  bank_name    VARCHAR(50)  NOT NULL DEFAULT '',
  bank_account VARCHAR(50)  NOT NULL DEFAULT '',  -- 수납계좌는 손님에게 안내하는 값이라 암호화하지 않는다
  bank_holder  VARCHAR(50)  NOT NULL DEFAULT '',
  sort         INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_complex_exposed (exposed, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 세대 — 단지+동+호가 하나의 등기진행 단위. 공동명의는 owner 로 푼다.
CREATE TABLE IF NOT EXISTS household (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  complex_id INT UNSIGNED NOT NULL,
  dong       VARCHAR(10) NOT NULL,
  ho         VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_household (complex_id, dong, ho),
  CONSTRAINT fk_household_complex FOREIGN KEY (complex_id) REFERENCES complex(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 소유자(명의인) — 공동명의는 한 세대에 여러 행. 로그인 대조는 이름+생년월일6.
CREATE TABLE IF NOT EXISTS owner (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  household_id INT UNSIGNED NOT NULL,
  name         VARCHAR(50) NOT NULL,
  birth6       CHAR(6) NOT NULL,                  -- 주민번호 앞 6자리(생년월일)
  phone        VARCHAR(20) NOT NULL DEFAULT '',
  is_primary   TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_owner_household (household_id),
  KEY ix_owner_login (name, birth6),
  CONSTRAINT fk_owner_household FOREIGN KEY (household_id) REFERENCES household(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 등기진행 — 세대당 1행. 기존 8단계를 그대로 재현한다.
-- 날짜가 채워지면 그 단계는 완료. 화면 상태(완료/진행중/준비중)는
-- 날짜 유무로 계산한다.
--   step1 등기서류수령 → step2 취득세신고 → step3 등기비용통보 →
--   step4 등기비입금확인 → step5 건설사등기서류수령 →
--   step6 등기소서류접수 → step7 등기완료 → step8 권리증교부
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS progress (
  household_id INT UNSIGNED NOT NULL,
  step1_date DATE NULL, step2_date DATE NULL, step3_date DATE NULL, step4_date DATE NULL,
  step5_date DATE NULL, step6_date DATE NULL, step7_date DATE NULL, step8_date DATE NULL,
  missing_docs TEXT NULL,                         -- 미비서류(줄 단위 텍스트, 기존과 동일)
  memo_admin   TEXT NULL,                         -- 관리자메모(손님에게 안 보임)
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (household_id),
  CONSTRAINT fk_progress_household FOREIGN KEY (household_id) REFERENCES household(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 등기비용 내역서 — 세대당 1행. 기존 11항목 + 합계/입금액/차액.
-- 금액은 원 단위 정수(BIGINT). 차액 = 입금액 - 합계 (기존 엑셀 36열과 동일,
-- 음수면 아직 덜 입금된 상태).
CREATE TABLE IF NOT EXISTS cost (
  household_id  INT UNSIGNED NOT NULL,
  acq_tax       BIGINT NOT NULL DEFAULT 0,        -- 취득세
  bond_transfer BIGINT NOT NULL DEFAULT 0,        -- 이전채권
  bond_mortgage BIGINT NOT NULL DEFAULT 0,        -- 설정채권
  stamp_tax     BIGINT NOT NULL DEFAULT 0,        -- 인지대
  cert_stamp    BIGINT NOT NULL DEFAULT 0,        -- 증지대
  via_stamp     BIGINT NOT NULL DEFAULT 0,        -- 경유증표
  trust_cancel  BIGINT NOT NULL DEFAULT 0,        -- 신탁말소
  cert_fees     BIGINT NOT NULL DEFAULT 0,        -- 제증명
  fee           BIGINT NOT NULL DEFAULT 0,        -- 보수료
  vat           BIGINT NOT NULL DEFAULT 0,        -- 부가세
  etc_amt       BIGINT NOT NULL DEFAULT 0,        -- 기타
  total         BIGINT NOT NULL DEFAULT 0,        -- 합계
  paid_amount   BIGINT NOT NULL DEFAULT 0,        -- 입금액
  diff          BIGINT NOT NULL DEFAULT 0,        -- 차액
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (household_id),
  CONSTRAINT fk_cost_household FOREIGN KEY (household_id) REFERENCES household(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 손님 등록 데이터 — 주소/계좌는 sodium secretbox 로 암호화해서
-- base64 텍스트로 저장한다. 평문은 어디에도 남지 않는다.
-- 세대당 최신 1건을 쓰되, 이력 보존을 위해 행을 쌓는다(최신 = MAX(id)).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS address_request (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  household_id INT UNSIGNED NOT NULL,
  zip          VARCHAR(10)  NOT NULL DEFAULT '',
  addr1_enc    VARCHAR(700) NOT NULL,             -- 기본주소(암호화)
  addr2_enc    VARCHAR(700) NOT NULL DEFAULT '',  -- 상세주소(암호화)
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_addr_household (household_id, id),
  CONSTRAINT fk_addr_household FOREIGN KEY (household_id) REFERENCES household(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refund_request (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  household_id INT UNSIGNED NOT NULL,
  bank         VARCHAR(50)  NOT NULL,
  account_enc  VARCHAR(700) NOT NULL,             -- 계좌번호(암호화)
  holder       VARCHAR(50)  NOT NULL,
  refund_date  DATE NULL,                         -- 환불일(직원이 입력, 2단계)
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_refund_household (household_id, id),
  CONSTRAINT fk_refund_household FOREIGN KEY (household_id) REFERENCES household(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 자주묻는질문 — 분류는 기존 4개 고정.
CREATE TABLE IF NOT EXISTS faq (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category  ENUM('등기','비용','서류','채권환불') NOT NULL,
  question  VARCHAR(300) NOT NULL,
  answer    TEXT NOT NULL,
  sort      INT NOT NULL DEFAULT 0,
  visible   TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_faq_list (visible, category, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 손님 로그인 기록 — 세대 단위 잠금(1시간 10회 실패)의 근거 데이터.
-- 실패 시에도 입력한 단지/동/호를 남겨 시도 패턴을 볼 수 있게 한다.
-- 이름·생년월일은 남기지 않는다.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  complex_id INT UNSIGNED NULL,
  dong       VARCHAR(10) NOT NULL DEFAULT '',
  ho         VARCHAR(10) NOT NULL DEFAULT '',
  ok         TINYINT(1) NOT NULL DEFAULT 0,
  ip         VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_login_lockout (complex_id, dong, ho, ok, created_at),
  KEY ix_login_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 손님 작업기록 — 손님이 스스로 등록한 주소/환불 신청의 흔적.
-- (staff_audit 의 손님판. 값 원문은 남기지 않는다.)
CREATE TABLE IF NOT EXISTS customer_log (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  household_id INT UNSIGNED NOT NULL,
  owner_name   VARCHAR(50) NOT NULL DEFAULT '',
  action       VARCHAR(60) NOT NULL,              -- 'address.save' | 'refund.save' | 'login'
  ip           VARCHAR(45) NOT NULL DEFAULT '',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_clog_household (household_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMS 발송 내역 — 2단계(관리자)에서 사용. 지금은 자리만 잡아 둔다.
CREATE TABLE IF NOT EXISTS sms_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id   INT UNSIGNED NULL,
  complex_id INT UNSIGNED NULL,                   -- 아파트 단위 발송 대상
  msg_type   ENUM('SMS','LMS') NOT NULL DEFAULT 'SMS',
  sender     VARCHAR(20) NOT NULL DEFAULT '',     -- 사전등록 발신번호 (1899-4252)
  body       TEXT NOT NULL,
  target_cnt INT NOT NULL DEFAULT 0,
  status     VARCHAR(20) NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sms_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 직원 계정 자리표시 시드 — 비밀번호 해시는 tools/make_staff.php 로
-- 만들어 UPDATE 한다. 이 상태(빈 해시)로는 로그인되지 않는다.
-- ---------------------------------------------------------------------
INSERT INTO staff (login_id, name, role, pass_hash)
SELECT 'admin1', '관리자', 'admin', ''
WHERE NOT EXISTS (SELECT 1 FROM staff WHERE login_id = 'admin1');
