-- =====================================================================
-- 4단계(보안 강화) 마이그레이션
-- schema.sql + schema_phase2.sql + schema_phase3.sql 적용 후 실행한다.
-- 추가 전용(CREATE/ADD IF NOT EXISTS)이라 여러 번 실행해도 안전하다 (MariaDB 10.2+).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 손님 문자 인증번호 — 코드 원문은 저장하지 않는다 (HMAC-SHA256 해시만).
--   purpose: login(로그인 2단계) · reauth(환불계좌·주소 변경 전 재인증)
--   3분 유효 · 5회 오입력 시 revoked=1 · 사용 즉시 used_at
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_code (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  household_id INT UNSIGNED NOT NULL,
  owner_id     INT UNSIGNED NOT NULL,
  purpose      VARCHAR(12)  NOT NULL DEFAULT 'login',
  code_hash    CHAR(64)     NOT NULL,
  salt         CHAR(32)     NOT NULL,
  phone_tail   VARCHAR(4)   NOT NULL DEFAULT '',     -- 발송 번호 뒤 4자리(화면 안내용)
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  revoked      TINYINT(1)   NOT NULL DEFAULT 0,
  expires_at   DATETIME     NOT NULL,
  used_at      DATETIME     NULL DEFAULT NULL,
  ip           VARCHAR(45)  NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_otp_owner (owner_id, purpose, id),
  KEY ix_otp_created (created_at),
  CONSTRAINT fk_otp_household FOREIGN KEY (household_id) REFERENCES household(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 직원 TOTP(RFC 6238) — 비밀키는 sodium secretbox 로 암호화(crypto_key).
-- last_step: 마지막으로 통과한 30초 단계 번호 — 같은 코드 재사용 금지.
-- enabled=0 은 등록 화면에서 확인 코드를 넣기 전 상태.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_totp (
  staff_id    INT UNSIGNED NOT NULL,
  secret_enc  VARCHAR(255) NOT NULL,
  enabled     TINYINT(1)   NOT NULL DEFAULT 0,
  last_step   BIGINT       NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enabled_at  DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (staff_id),
  CONSTRAINT fk_totp_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 복구코드 8개 — HMAC 해시만 저장, 1회용(used_at).
CREATE TABLE IF NOT EXISTS staff_recovery_code (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id   INT UNSIGNED NOT NULL,
  code_hash  CHAR(64)     NOT NULL,
  used_at    DATETIME     NULL DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_recovery_staff (staff_id),
  CONSTRAINT fk_recovery_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP 계정 단위 실패 잠금 — 5회 틀리면 15분 (IP 와 무관하게 계정 기준). 비밀번호 통과로는 풀리지 않는다.
ALTER TABLE staff_totp
  ADD COLUMN IF NOT EXISTS fail_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_step,
  ADD COLUMN IF NOT EXISTS fail_until DATETIME NULL DEFAULT NULL AFTER fail_count;

-- 인증 세대 번호 — 비밀번호 재설정·OTP 초기화·중지 때 1 올려 기존 세션을 끊는다.
-- 등록코드 — 비밀번호만 아는 사람이 OTP 를 스스로 등록하지 못하게, admin 이 발급한 1회용 코드
-- (password_hash, 24시간 유효)를 넣어야 등록 화면의 비밀키가 열린다.
ALTER TABLE staff
  ADD COLUMN IF NOT EXISTS auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_active,
  ADD COLUMN IF NOT EXISTS enroll_hash  VARCHAR(255) NULL DEFAULT NULL AFTER auth_version,
  ADD COLUMN IF NOT EXISTS enroll_until DATETIME NULL DEFAULT NULL AFTER enroll_hash;

-- ---------------------------------------------------------------------
-- IP 차단 — 한 IP 가 10분에 서로 다른 대상 5곳 이상 또는 30회 이상
-- 실패하면 1시간 차단 (손님 로그인·문자 인증·직원 로그인·직원 OTP 공통).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ip_block (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip            VARCHAR(45)  NOT NULL,
  reason        VARCHAR(190) NOT NULL DEFAULT '',
  blocked_until DATETIME     NOT NULL,
  released_by   INT UNSIGNED NULL DEFAULT NULL,      -- 직원이 수동 해제한 경우
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ipblock (ip, blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- login_log 를 실패 집계 공용 원장으로 넓힌다.
--   kind:   customer(손님 로그인) · sms_otp(손님 문자 인증) · staff(직원 비밀번호) · staff_otp(직원 OTP)
--   target: 직원 아이디 / staff:N 등 세대가 아닌 대상
-- 기존 행은 kind='customer' 로 남아 1~3단계 집계가 그대로 맞는다.
ALTER TABLE login_log
  ADD COLUMN IF NOT EXISTS kind   VARCHAR(12) NOT NULL DEFAULT 'customer' AFTER id,
  ADD COLUMN IF NOT EXISTS target VARCHAR(60) NOT NULL DEFAULT '' AFTER ho;

-- 문자 발송 기록 — 인증번호 발송도 남기되 본문의 번호는 가린다.
ALTER TABLE sms_log
  ADD COLUMN IF NOT EXISTS purpose   VARCHAR(20) NOT NULL DEFAULT '' AFTER audience,
  ADD COLUMN IF NOT EXISTS to_masked VARCHAR(20) NOT NULL DEFAULT '' AFTER purpose,
  ADD COLUMN IF NOT EXISTS driver    VARCHAR(10) NOT NULL DEFAULT '' AFTER to_masked;

-- ---------------------------------------------------------------------
-- 관리자 IP 허용목록 — setting 키 admin_ip_allowlist (줄 단위 IP/CIDR).
-- 비어 있으면 미적용. 표 추가 없음.
-- ---------------------------------------------------------------------
